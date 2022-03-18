<?php


namespace app\models;


use app\models\database\Archive_doctor;
use app\models\database\Emails;
use app\models\database\Archive_execution_area;
use app\models\database\FirebaseClient;
use app\models\database\Archive_complex_execution_info;
use app\models\utils\DownloadHandler;
use app\models\utils\GrammarHandler;
use app\models\utils\MailHandler;
use app\models\utils\PriceHandler;
use app\models\utils\TimeHandler;
use JsonException;
use RuntimeException;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class Api
{

    /**
     * Обработка запроса
     * @return array
     * @throws Exception
     * @throws JsonException
     * @throws StaleObjectException
     * @throws Throwable
     */
    public static function handleRequest(): array
    {
        $request = Yii::$app->getRequest();
        if (!empty($request->bodyParams['cmd'])) {
            $cmd = $request->bodyParams['cmd'];
            switch ($cmd) {
                case 'getPrices':
                    return ['status' => 'success', 'prices' => (new PriceHandler())->getPrices()];
                case 'get_login_qr':
                    return self::getLoginQr();
                case 'get_center_info':
                    return self::getCenterInfo();
                case 'get_whole_info':
                    return self::getWholeInfo();
                case 'sendMail':
                    return self::sendMail();
                case 'addMail':
                    return self::addMail();
                case 'getArchiveSearchInitials':
                    return self::getArchiveSearchInitials();
                case 'archiveSearch':
                    return self::doArchiveSearch();
                case 'delete_execution':
                    return self::deleteExecution();
                case 'delete_conclusions':
                    return self::deleteConclusions();
                case 'login' :
                    $login = $request->bodyParams['login'];
                    $pass = $request->bodyParams['pass'];
                    $admin = User::getAdmin();
                    if ($login === $admin->username && $admin->validatePassword($pass)) {
                        // верные данные для входа, верну токен
                        return ['status' => 'success', 'token' => $admin->access_token];
                    }
                    return ['status' => 'failed', 'message' => 'Неверный логин или пароль'];
                case 'check_access_token':
                    if (self::token_valid($request->bodyParams['token'])) {
                        return ['status' => 'success'];
                    }
                    break;
                case 'userLogin':
                    $login = GrammarHandler::toLatin($request->bodyParams['login']);
                    $pass = $request->bodyParams['pass'];
                    try {
                        $firebaseToken = $request->bodyParams['firebaseToken'];
                    } catch (\Exception $e) {
                        Telegram::sendDebug($e->getTraceAsString());
                    }
                    $user = User::findByUsername($login);
                    if ($user !== null) {
                        if ($user->failed_try < 10) {
                            if ($user->validatePassword($pass)) {
                                if (!empty($firebaseToken)) {
                                    FirebaseClient::register($user, $firebaseToken);
                                }
                                return ['status' => 'success', 'auth_token' => $user->access_token, 'execution_id' => $user->username];
                            }
                            ++$user->failed_try;
                            $user->save();
                        }
                        Telegram::sendDebug("try to enter in $user->username with password $pass");
                    }
                    return ['status' => 'failed', 'message' => 'wrong data'];
                case 'checkAuthToken':
                    $authToken = $request->bodyParams['authToken'];
                    if (!empty($authToken)) {
                        $user = User::findIdentityByAccessToken($authToken);
                        if ($user !== null) {
                            try {
                                $firebaseToken = $request->bodyParams['firebaseToken'];
                            } catch (\Exception $e) {
                                Telegram::sendDebug($e->getTraceAsString());
                            }
                            if (!empty($firebaseToken)) {
                                FirebaseClient::register($user, $firebaseToken);
                            }
                            return ['status' => 'success', 'execution_id' => $user->username];
                        }
                    }
                    return ['status' => 'failed', 'message' => 'invalid token'];
                case 'get_execution_info':
                    $authToken = $request->bodyParams['token'];
                    if (!empty($authToken)) {
                        $user = User::findIdentityByAccessToken($authToken);
                        if ($user !== null) {
                            $filesInfo = Table_availability::getFilesInfo($user);
                            return [
                                'status' => 'success',
                                'executionId' => $user->username,
                                'patientName' => Table_availability::getPatientName($user->username),
                                'files' => $filesInfo
                            ];
                        }
                        return ['status' => 'failed', 'message' => 'invalid token'];
                    }
                    break;
                case 'get_executions_list':
                    $authToken = $request->bodyParams['token'];
                    $user = User::findIdentityByAccessToken($authToken);
                    if ($user !== null) {
                        // get list of executions for current user
                        $executionInfo = ExecutionHandler::getExecutionInfo($user);
                        return ['status' => 'success',
                            'list' => ExecutionHandler::getExecutionsList($user),
                            'executionId' => $executionInfo->executionId,
                            'executionType' => $executionInfo->executionType,
                            'executionDate' => TimeHandler::timestampToDate($executionInfo->executionDate)
                        ];
                    }
                    break;
                case 'userLogout':
                    $authToken = $request->bodyParams['authToken'];
                    $firebaseToken = $request->bodyParams['authToken'];
                    $user = User::findIdentityByAccessToken($authToken);
                    if ($user !== null) {
                        // delete subscriptions for this firebase token
                        FirebaseClient::unregister($firebaseToken);
                        return ['status' => 'success'];
                    }
                    return ['status' => 'failed', 'message' => 'user not found'];
            }
            return ['status' => 'failed', 'message' => 'unknown action'];
        }

        if (!empty($request->bodyParams['json'])) {
            try {
                $json = json_decode($request->bodyParams['json'], true, 2, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                Telegram::sendDebug($e->getTraceAsString());
                return ['status' => 'error handle json'];
            }
            $cmd = $json['cmd'];
            $token = $json['token'];
            if (!self::token_valid($token)) {
                Telegram::sendDebug("Попытка подключения к API с неверным токеном");
                return ['status' => 'unauthorized'];
            }
            if ($cmd === 'upload_file') {
                $file = UploadedFile::getInstanceByName('my_file');
                if ($file !== null) {
                    if ($file->getExtension() === 'zip') {
                        // распакую полученный файл в папку с обследованиями
                        return FileUtils::extractZip($file);
                    }

// обработаю файл
                    $root = Yii::$app->basePath;
                    // создам временную папку, если её ещё не существует
                    if (!is_dir($root . '/temp') && !mkdir($concurrentDirectory = $root . '/temp') && !is_dir($concurrentDirectory)) {
                        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                    }
                    $tempName = str_replace(['-', '_'],['', ''], Yii::$app->security->generateRandomString(50));
                    $fileName = $root . "\\temp\\" . $tempName . GrammarHandler::getFileExtension($file->name);
                    $file->saveAs($fileName);
                    try {
                        Telegram::sendDebug("handle api file $fileName");
                        $path = FileUtils::handleFileUpload($fileName);
                        if ($path !== null) {
                            return ['status' => 'success', 'path' => $path];
                        }
                    } catch (Exception $e) {
                        Telegram::sendDebugFile($fileName);
                        return ['status' => 'failed', 'message' => $e->getMessage()];
                    }
                }
                return ['status' => 'failed', 'message' => 'unknown command: ' . $cmd];
            }
        }
        return ['status' => 'success'];
    }

    private static function token_valid($token): bool
    {
        $user = User::findIdentityByAccessToken($token);
        return $user !== null && $user->username === User::ADMIN_NAME;
    }

    /**
     * @throws NotFoundHttpException
     */
    public static function handleFileRequest(): void
    {
        $request = Yii::$app->getRequest();
        $cmd = $request->bodyParams['cmd'];
        if ($cmd === 'get_file') {
            $authToken = $request->bodyParams['token'];
            if (!empty($authToken)) {
                $user = User::findIdentityByAccessToken($authToken);
                if ($user !== null) {
                    if ($user->username === User::ADMIN_NAME) {
                        $type = $request->bodyParams['type'];
                        $executionId = $request->bodyParams['execution_id'];
                        if ($type === 'archive') {
                            FileUtils::loadFileForApi($executionId, $type);
                        } else {
                            $conclusionName = $request->bodyParams['conclusion_offset'];
                            $conclusionRealName = null;
                            try{
                                $conclusionRealName = urldecode($request->bodyParams['conclusion_name']);
                            }
                            catch (\Exception){}
                            FileUtils::loadConclusionForApi($executionId, $conclusionName, $conclusionRealName);
                        }
                    } else if (!empty($request->bodyParams['file_name'])) {
                        $file = $request->bodyParams['file_name'];
                        try {
                            FileUtils::loadFile($file, $user);
                        } catch (Throwable $e) {
                            Telegram::sendDebug($e->getMessage());
                            throw new NotFoundHttpException("file download error");
                        }
                    } else {
                        $archiveFile = $request->bodyParams['archive_file_name'];
                        if (!empty($archiveFile)) {
                            try {
                                FileUtils::loadArchiveFile((int)$archiveFile);
                            } catch (Throwable $e) {
                                Telegram::sendDebug($e->getMessage());
                                throw new NotFoundHttpException("file download error");
                            }
                        }
                    }
                }
            }
        } else if ($cmd === 'get_archive_file') {
            $authToken = $request->bodyParams['token'];
            if (!empty($authToken)) {
                $user = User::findIdentityByAccessToken($authToken);
                if (($user !== null) && $user->username === User::ADMIN_NAME) {
                    $id = $request->bodyParams['execution_id'];
                    if (!empty($id)) {
                        DownloadHandler::apiDownloadArchive($id);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    private static function getCenterInfo(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            // get today info by center
            $info = ExecutionHandler::getTodayCenterInfo();
            return ['status' => 'success', 'info' => $info];
        }
        return ['status' => 'failed', 'message' => 'empty or wrong token'];
    }

    /**
     * @return array
     * @throws \Exception
     */
    private static function getWholeInfo(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            // get today info by center
            $info = ExecutionHandler::getWholeInfo();
            return ['status' => 'success', 'info' => $info];
        }
        return ['status' => 'failed', 'message' => 'empty or wrong token'];
    }

    /**
     * @throws Throwable
     */
    private static function deleteExecution(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            $model = new AdministratorActions(['scenario' => AdministratorActions::SCENARIO_DELETE_ITEM]);
            $model->executionId = $request->bodyParams['execution_id'];
            return $model->deleteItem();
        }
        return ['status' => 'failed', 'message' => 'empty or wrong token'];
    }

    /**
     * @throws Throwable
     */
    private static function deleteConclusions(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            ExecutionHandler::deleteAllConclusions($request->bodyParams['execution_id']);
            return ['status' => 1];
        }
        return ['status' => 'failed', 'message' => 'empty or wrong token'];
    }

    /**
     * @return string[]
     * @throws Exception
     */
    private static function sendMail(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            $user = User::findByUsername($request->bodyParams['execution_id']);
            if ($user !== null) {
                return MailHandler::sendInfoMail($user->id);
            }
        }
        return ['status' => 'failed', 'message' => 'empty or wrong token'];
    }

    private static function addMail(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            $user = User::findByUsername($request->bodyParams['execution_id']);
            if ($user !== null) {
                return Emails::addMail($user, $request->bodyParams['email']);
            }
        }
        return ['status' => 'failed', 'message' => 'empty or wrong token'];
    }

    private static function getArchiveSearchInitials(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            $doctors = Archive_doctor::getDoctorsArray();
            $areas = Archive_execution_area::getAreasArray();
            return ['status' => 1, 'doctors' => $doctors, 'areas' => $areas];
        }
        return ['status' => 0, 'message' => 'empty or wrong token'];
    }

    /**
     * @return array
     */
    private static function doArchiveSearch(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            $executionId = $request->bodyParams['executionId'];
            $patientPersonals = urldecode($request->bodyParams['patientPersonals']);
            $executionDateStart = $request->bodyParams['executionDateStart'];
            $executionDateFinish = $request->bodyParams['executionDateFinish'];
            $doctor = $request->bodyParams['doctor'];
            $birthdate = $request->bodyParams['birthdate'];
            $area = $request->bodyParams['area'];
            $fullTextSearch = urldecode($request->bodyParams['fulltextSearch']);
            return Archive_complex_execution_info::request(
                $executionId,
                $patientPersonals,
                $executionDateStart,
                $executionDateFinish,
                $doctor,
                $area,
                $fullTextSearch,
                $birthdate
            );
        }
        return ['status' => 0, 'message' => 'empty or wrong token'];
    }

    private static function getLoginQr(): array
    {
        $request = Yii::$app->getRequest();
        $authToken = $request->bodyParams['token'];
        if (!empty($authToken) && self::token_valid($authToken)) {
            $executionId = $request->bodyParams['executionId'];
            $user = User::findByUsername($executionId);
            return ['status' => 1, 'qr' => (new LoginQr($user))->getStringQr()];
        }
        return ['status' => 0, 'message' => 'empty or wrong token'];
    }
}