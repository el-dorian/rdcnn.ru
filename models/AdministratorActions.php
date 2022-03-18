<?php /** @noinspection PhpUndefinedClassInspection */


namespace app\models;


use app\models\database\Emails;
use app\models\utils\FirebaseHandler;
use app\priv\Info;
use JetBrains\PhpStorm\ArrayShape;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\db\StaleObjectException;
use yii\web\UploadedFile;

class AdministratorActions extends Model
{
    public const SCENARIO_CHANGE_PASSWORD = 'change_password';
    public const SCENARIO_DELETE_ITEM = 'delete_item';
    public const SCENARIO_ADD_EXECUTION = 'add_execution';
    public const SCENARIO_ADD_CONCLUSION = 'add_conclusion';

    public $executionId;
    /**
     * @var UploadedFile
     */
    public $execution;
    /**
     * @var UploadedFile[]
     */
    public $conclusion;

    /**
     * @return array
     * @throws Exception
     */
    public static function checkPatients($startCheckStatus): array
    {
        $response = [];
        // тут придётся проверять наличие неопознанных папок
        $unhandledFolders = FileUtils::checkUnhandledFolders();
        $waitingFolders = FileUtils::checkWaitingFolders();
        $response['waitingFolders'] = $waitingFolders;
        $response['unhandledFolders'] = $unhandledFolders;
        $response['patientList'] = [];
        // верну список пациентов со статусами данных
        $patientsList = User::findAllRegistered();
        if(!empty($patientsList)){
            /** @var User $item */
            foreach ($patientsList as $item) {
                if(!empty(Yii::$app->session['center']) && Yii::$app->session['center'] !== 'all' && Utils::isFiltered($item)){
                    continue;
                }
                    // проверю, загружены ли данные по пациенту
                    $patientInfo = [];
                    $patientInfo['id'] = $item->username;
                    $patientInfo['real_id'] = $item->id;
                    $patientInfo['execution'] = ExecutionHandler::isExecution($item->username);
                    $patientInfo['conclusionsCount'] = ExecutionHandler::countConclusions($item->username);
                    if($patientInfo['conclusionsCount'] > 0){
                        $patientInfo['conclusion_text'] = ExecutionHandler::getConclusionText($item->username);
                        // попробую найти имя пациента
                        $availItems = Table_availability::findAll(['userId' => $item->username, 'is_conclusion' => 1]);
                        if($availItems !== null && count($availItems) > 0){
                            $patientInfo['patient_name'] = $availItems[0]->patient_name;
                            $areas = [];
                            foreach ($availItems as $availItem) {
                                $areas[] = $availItem->execution_area;
                            }
                            $patientInfo['conclusion_areas'] = $areas;
                        }
                    }
                    $mailInfo = Emails::findOne(['patient_id' => $item->id]);
                    $patientInfo['hasMail'] = (bool) $mailInfo;
                    if($mailInfo !== null){
                        $patientInfo['mailed'] = $mailInfo->mailed_yet;
                    }
                    $response['patientList'][] = $patientInfo;
            }
            $response['startCheckStatus'] = $startCheckStatus;
        }
        return $response;
    }

    /** @noinspection OnlyWritesOnParameterInspection */
    public static function selectCenter(): void
    {
        $center =  Yii::$app->request->post('center');
        $session = Yii::$app->session;
        $session['center'] = $center;
    }

    /** @noinspection OnlyWritesOnParameterInspection */
    public static function selectTime(): void
    {
        $time =  Yii::$app->request->post('timeInterval');
        $session = Yii::$app->session;
        $session['timeInterval'] = $time;
    }


    /** @noinspection OnlyWritesOnParameterInspection */
    public static function selectSort(): void
    {
        $sortBy =  Yii::$app->request->post('sortBy');
        $session = Yii::$app->session;
        $session['sortBy'] = $sortBy;
    }

    /**
     * @param User $user
     * @param bool $saveToArchive
     */
    public static function simpleDeleteItem(User $user, bool $saveToArchive = false): void
    {
        Telegram::sendDebug("deleting $user->username");
        // search firebase bindings for user. if it exists- notify about deleting and delete
        try {
            FirebaseHandler::notifyExecutionIsDeleted($user);
        } catch (StaleObjectException | Throwable $e) {
            Telegram::sendDebug("Не удалось оповестить пользователя об удалении учётной записи, причина: " . $e->getMessage());
        }
        $conclusionFile = Info::CONC_FOLDER . '\\' . $user->username . '.pdf';
        if(is_file($conclusionFile)){
            try{
                Telegram::sendDebug("delete " . $conclusionFile);
                unlink($conclusionFile);
            }
            catch (\Exception $exception){
                Telegram::sendDebug("error delete conclusion: " . $exception->getMessage());
            }
            // удалю также версию без фона, если она есть
            $conclusionFile = Info::CONC_FOLDER . '\\nb_' . $user->username . '.pdf';
            if(is_file($conclusionFile)) {
                if($saveToArchive){
                    Telegram::sendDebug("add to archive " . $conclusionFile);
                    // асинхронно добавлю файл в архив
                    $file = Yii::$app->basePath . '\\yii.bat';
                    if (is_file($file)) {
                        $command = "$file console/archive $conclusionFile";
                        exec($command);
                    }
                }
                else{
                    Table_availability::deleteUserData($user->username);
                    try{
                        Telegram::sendDebug("delete " . $conclusionFile);
                        unlink($conclusionFile);
                    }
                    catch (\Exception $exception){
                        Telegram::sendDebug("error delete conclusion: " . $exception->getMessage());
                    }
                }
            }
        }
        $executionFile = Info::EXEC_FOLDER . '\\' . $user->username . '.zip';
        if(is_file($executionFile)){
            try{
                Telegram::sendDebug("delete $executionFile");
                unlink($executionFile);
            }
            catch (\Exception $exception){
                Telegram::sendDebug("error delete execution: " . $exception->getMessage());
            }
        }
        ExecutionHandler::deleteAddConcs($user->username);
        if(!$saveToArchive){
            $execution = User::findByUsername($user->username);
            if($execution !== null){
                // удалю запись в таблице выдачи разрешений
                $auth = Table_auth_assigment::findOne(['user_id' => $execution->id]);
                if($auth !== null){
                    try {
                        Telegram::sendDebug("delete auth rules");
                        $auth->delete();
                    } catch (StaleObjectException | Throwable) {
                        Telegram::sendDebug("error delete auth rules");
                    }
                }
                try {
                    $execution->delete();
                } catch (StaleObjectException | Throwable) {
                    Telegram::sendDebug("error delete user");
                }
                // если пользователь залогинен и это не админ-выхожу из учётной записи
                if(!empty(Yii::$app->user) && !Yii::$app->user->can('manage')){
                    Yii::$app->user->logout(true);
                }
            }
        }
        else{
            $user->status = 2;
            $user->save();
        }
    }

    #[ArrayShape([self::SCENARIO_CHANGE_PASSWORD => "string[]", self::SCENARIO_DELETE_ITEM => "string[]", self::SCENARIO_ADD_EXECUTION => "string[]", self::SCENARIO_ADD_CONCLUSION => "string[]"])] public function scenarios() :array
    {
        return [
            self::SCENARIO_CHANGE_PASSWORD => ['executionId'],
            self::SCENARIO_DELETE_ITEM => ['executionId'],
            self::SCENARIO_ADD_EXECUTION => ['executionId', 'execution'],
            self::SCENARIO_ADD_CONCLUSION => ['executionId', 'conclusion'],
        ];
    }

    public function rules(): array
    {
        return [
            ['executionId', 'string', 'length' => [1, 255]],
            [['executionNumber'], 'required', 'on' => [self::SCENARIO_CHANGE_PASSWORD, self::SCENARIO_DELETE_ITEM, self::SCENARIO_ADD_EXECUTION, self::SCENARIO_ADD_CONCLUSION]],
            ['executionId', 'match', 'pattern' => '/^[a-z0-9]+$/iu'],
            [['execution'], 'file', 'skipOnEmpty' => true, 'extensions' => 'zip', 'maxSize' => 1048576000],
            [['conclusion'], 'file', 'skipOnEmpty' => true, 'extensions' => 'pdf', 'maxSize' => 104857600, 'maxFiles' => 10],
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function changePassword(): array
    {
        if($this->validate()){
            $execution = User::findByUsername($this->executionId);
            if($execution === null){
                return ['status' => 3, 'message' => 'Обследование не найдено'];
            }
            $password = User::generateNumericPassword();
            $hash = Yii::$app->getSecurity()->generatePasswordHash($password);
            $execution->password_hash = $hash;
            $execution->failed_try = 0;
            $execution->status = 1;
            $execution->save();
            // remove it from black list

            return ['status' => 1, 'message' => 'Пароль пользователя успешно изменён на <b class="text-success">' . $password . '</b> <button class="btn btn-default" id="copyPassBtn" data-password="' . $password . '"><span class="text-success">Копировать пароль</span></button> '];
        }
        return ['status' => 2, 'message' => $this->errors];
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function deleteItem(): array
    {
        if($this->validate()){
            $execution = User::findByUsername($this->executionId);
            if($execution === null){
                return ['status' => 1, 'header' => 'Неудача', 'message' => 'Обследование не найдено'];
            }
            self::simpleDeleteItem($execution);
            return ['status' => 1, 'message' => 'Обследование удалено<script>$("tr[data-id=\'' . $this->executionId . '\']").remove()</script>'];
        }
        return ['status' => 2, 'message' => $this->errors];
    }

}