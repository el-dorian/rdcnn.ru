<?php /** @noinspection PhpUndefinedClassInspection */


namespace app\models;


use app\models\database\AuthAssignment;
use app\models\database\Emails;
use app\models\database\NotificationSendingInfo;
use app\models\database\Archive_complex_execution_info;
use app\models\database\TempDownloadLinks;
use app\models\database\ViberSubscriptions;
use app\models\selections\ExecutionInfo;
use app\models\utils\FilesHandler;
use app\models\utils\FirebaseHandler;
use app\models\utils\GrammarHandler;
use app\models\utils\TimeHandler;
use app\priv\Info;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\db\StaleObjectException;

class ExecutionHandler extends Model
{
    public const SCENARIO_ADD = 'add';
    public const CENTER_AURORA = 1;
    public const CENTER_NV = 2;
    public const EXECUTION_TYPE_CURRENT = 1;
    public const EXECUTION_TYPE_ARCHIVE = 2;

    /**
     * @return array
     * @throws Throwable
     */
    public static function checkAvailability(): array
    {
        // получу информацию о пациенте
        if (Yii::$app->user->can('manage')) {
            $referer = $_SERVER['HTTP_REFERER'];
            $id = explode('/', $referer)[4];
        } else {
            $id = Yii::$app->user->identity->username;
        }
        $user = User::findByUsername($id);
        if ($user !== null) {
            $isExecution = self::isExecution($id);
            $conclusions = Table_availability::getConclusions($id);
            $timeLeft = 0;
            // посмотрю, сколько времени ещё будет доступно обследование
            $startTime = $user->updated_at;
            if (!empty($startTime)) {
                // найдено время старта
                $now = time();
                $lifetime = $startTime + Info::DATA_SAVING_TIME;
                if ($now < $lifetime) {
                    $timeLeft = Utils::secondsToTime($lifetime - $now);
                } else {
                    AdministratorActions::simpleDeleteItem($user, true);
                    return ['status' => 2];
                }
            }
            return ['status' => 1, 'execution' => $isExecution, 'conclusions' => $conclusions, 'timeLeft' => $timeLeft];
        }
        return [];
    }

    /**
     * @throws Exception
     */
    #[ArrayShape(['status' => "int", 'header' => "string", 'message' => "string"])] public static function checkFiles($executionNumber): array
    {
        $executionDir = Yii::getAlias('@executionsDirectory') . '\\' . $executionNumber;
        if (is_dir($executionDir)) {
            self::packFiles($executionNumber, $executionDir);
            return ['status' => 1, 'header' => '<h2 class="text-center text-success">Успех</h2>', 'message' => '<p class="text-success text-center">Папка найдена и успешно обработана</p>'];
        }
        return ['status' => 1, 'header' => '<h2 class="text-center text-danger">Неудача</h2>', 'message' => '<p class="text-center text-danger">Папка не найдена</p>'];

    }

    public static function rmRec($path): bool
    {
        if (is_file($path)) {
            return unlink($path);
        }
        if (is_dir($path)) {
            foreach (scandir($path, SCANDIR_SORT_NONE) as $p) {
                if (($p !== '.') && ($p !== '..')) {
                    self::rmRec($path . DIRECTORY_SEPARATOR . $p);
                }
            }
            return rmdir($path);
        }
        return false;
    }

    public static function isAdditionalConclusions(string $username): int
    {
        $searchPattern = '/' . $username . '[-.][0-9]+\.pdf/';
        $existentFiles = scandir(Info::CONC_FOLDER);
        $addsQuantity = 0;
        foreach ($existentFiles as $existentFile) {
            if (preg_match($searchPattern, $existentFile)) {
                $addsQuantity++;
            }
        }
        return $addsQuantity;
    }

    public static function deleteAddConcs($id): void
    {
        $searchPattern = '/' . $id . '[-.][0-9]+\.pdf/';
        if(is_dir(Info::CONC_FOLDER)){
            $existentFiles = scandir(Info::CONC_FOLDER);
            foreach ($existentFiles as $existentFile) {
                if (preg_match($searchPattern, $existentFile)) {
                    $path = Info::CONC_FOLDER . DIRECTORY_SEPARATOR . $existentFile;
                    if (is_file($path)) {
                        try{
                            unlink($path);
                        }
                        catch (\Exception $exception){
                            Telegram::sendDebug("error delete execution: " . $exception->getMessage());
                        }
                        $nbPath = Info::CONC_FOLDER . DIRECTORY_SEPARATOR . 'nb_' . $existentFile;
                        if (is_file($nbPath)) {
                            try{
                                unlink($nbPath);
                            }
                            catch (\Exception $exception){
                                Telegram::sendDebug("error delete execution: " . $exception->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public static function check(): void
    {
        // для начала-получу все данные о зарегистрированных файлах
        $availData = Table_availability::getRegistered();
        // проверю наличие папок
        if (!is_dir(Info::EXEC_FOLDER)) {
            return;
        }
        // проверю устаревшие данные,
        // получу всех пользователей
        $users = User::findActive();
        if (!empty($users)) {
            foreach ($users as $user) {
                // ищу данные по доступности обследований.
                if (($user->updated_at + Info::DATA_SAVING_TIME) < time()) {
                    Telegram::sendDebug("deactivate existent user " . $user->username);
                    AdministratorActions::simpleDeleteItem($user, true);
                    echo TimeHandler::timestampToDateTime(time()) . "user $user->username removed by timeout\n";
                }
            }
        }
        // автоматическая обработка папок
        $entities = array_slice(scandir(Info::EXEC_FOLDER), 2);
        // проверю папки
        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $path = Info::EXEC_FOLDER . '\\' . $entity;
                if (is_dir($path)) {
                    if (str_starts_with($path, Info::EXEC_FOLDER . '\\.')) {
                        Telegram::sendDebug("try to handle the root dir $path");
                        rename($path, Info::EXEC_FOLDER . '\\' . GrammarHandler::generateFileName());
                        continue;
                    }
                    // для начала проверю папку, если она изменена менее 5 минут назад- пропускаю её
                    $stat = stat($path);
                    $changeTime = $stat['mtime'];
                    $difference = time() - $changeTime;
                    if ($difference > 60) {
                        // вероятно, папка содержит файлы обследования
                        // проверю, что папка не пуста
                        if (count(scandir($path)) > 2) {
                            // папка не пуста
                            try {
                                Telegram::sendDebug("handle dicom dir $path");
                                FilesHandler::handleDicomDir($path);
                            } catch (\Exception $e) {
                                echo "Have handle error " . $e->getMessage();
                                Telegram::sendDebug("У нас ошибка обработки " . $e->getMessage());
                                continue;
                            }
                        } else {
                            // удалю папку
                            try {
                                self::rmRec($path);
                                echo TimeHandler::timestampToDateTime(time()) . "$entity empty dir removed \n";
                                Telegram::sendDebug(TimeHandler::timestampToDateTime(time()) . "$entity удалена пустая папка \n");
                            } catch (\Exception) {
                                FileUtils::writeUpdateLog('error delete dir ' . $path);
                                Telegram::sendDebug('error delete dir ' . $path);
                            }
                        }

                    } else {
                        echo TimeHandler::timestampToDateTime(time()) . "dir $entity waiting for timeout \n";
                    }
                } else if (is_file($path) && GrammarHandler::endsWith($path, '.zip') && !array_key_exists($entity, $availData)) {
                    // если обнаружен .zip - проверю, зарегистрирован ли он, если нет- проверю, содержит ли DICOM
                    echo "handle unregistered zip $entity \n";
                    try {
                        $result = FilesHandler::unzip($path);
                    } catch (\Exception $e) {
                        echo "Unzip error " . $e->getMessage();
                        Telegram::sendDebug("У нас ошибка распаковки " . $e->getMessage());
                        Telegram::sendDebug("У нас ошибка распаковки " . $e->getTraceAsString());
                        continue;
                    }
                    if ($result !== null) {
                        echo "added zip $result \n";
                        Telegram::sendDebug("added zip $result \n");
                    }
                }
            }
            // теперь перепроверю данные для получения актуальной информации о имеющихся файлах
            $entities = array_slice(scandir(Info::EXEC_FOLDER), 2);
            $pattern = '/^[AT]?\d+.zip$/';
            if (!empty($entities)) {
                foreach ($entities as $entity) {
                    $path = Info::EXEC_FOLDER . '/' . $entity;
                    if (is_file($path) && preg_match($pattern, $entity)) {
                        // найден файл, обработаю информацию о нём
                        $user = User::findByUsername(GrammarHandler::getBaseFileName($entity));
                        // если учётная запись не найдена- зарегистрирую
                        if ($user === null) {
                            self::createUser(GrammarHandler::getBaseFileName($entity));
                            $user = User::findByUsername(GrammarHandler::getBaseFileName($entity));
                        }
                        if (array_key_exists($entity, $availData)) {
                            $existentFile = $availData[$entity];
                            // проверю дату изменения и md5 файлов. Если они совпадают- ничего не делаю, если не совпадают- отправлю в вайбер уведомление об обновлении файла
                            //$md5 = md5_file($path);
                            $stat = stat($path);
                            $changeTime = $stat['mtime'];
                            if ($changeTime !== $existentFile->file_create_time) {
                                //if ($changeTime !== $existentFile->file_create_time && $md5 !== $existentFile->md5) {
                                // отправлю новую версию файла пользователю
                                $md5 = md5_file($path);
                                $existentFile->md5 = $md5;
                                $existentFile->file_create_time = $changeTime;
                                $existentFile->save();
                                FirebaseHandler::sendExecutionLoaded(
                                    $user->id,
                                    $entity,
                                    true
                                );
                                //Viber::notifyExecutionLoaded($user->username);
                            }
                        } else {
                            // внесу информацию о файле в базу
                            $md5 = md5_file($path);
                            $stat = stat($path);
                            $changeTime = $stat['mtime'];
                            if (Table_availability::isNewFile($md5, $entity)) {
                                $item =
                                    new Table_availability([
                                        'file_name' => $entity,
                                        'is_execution' => true,
                                        'md5' => $md5,
                                        'file_create_time' => $changeTime,
                                        'userId' => $user->username
                                    ]);
                                $item->save();

                                Telegram::sendExecutionAdded($item);

                                FirebaseHandler::sendExecutionLoaded(
                                    $user->id,
                                    $entity,
                                    false
                                );
                            } else {
                                $existent = Table_availability::findOne(['file_name' => $entity]);
                                if ($existent !== null) {
                                    $existent->md5 = $md5;
                                    $existent->is_notification_sent = 0;
                                    $existent->file_create_time = $changeTime;
                                    $existent->save();
                                    FirebaseHandler::sendExecutionLoaded(
                                        $user->id,
                                        $entity,
                                        true
                                    );
                                }
                            }
                            // оповещу мессенджеры о наличии файла
                            //Viber::notifyExecutionLoaded($user->username);
                        }
//                        else {
//                            $stat = stat($path);
//                            $changeTime = $stat['mtime'];
//                            if (time() > $changeTime + Info::DATA_SAVING_TIME) {
//                                // если нет связанной учётной записи- удалю файл
//                                echo TimeHandler::timestampToDate(time()) . " delete zip $entity with no account bind\n";
//                                // удалю файл
//                                self::rmRec($path);
//                            }
//                        }
                    }
                }
            }
        }
        $entity = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/logs';
        if (!is_dir($entity) && !is_dir($entity) && !mkdir($entity) && !is_dir($entity)) {
            echo(sprintf('Directory "%s" was not created', $entity));
        }


        if (!is_dir(Info::CONC_FOLDER) && !is_dir(Info::CONC_FOLDER) && !mkdir(Info::CONC_FOLDER) && !is_dir(Info::CONC_FOLDER)) {
            echo(sprintf('Directory "%s" was not created', Info::CONC_FOLDER));
        }

        // теперь обработаю заключения
        $conclusionsDir = Info::CONC_FOLDER;
        if (!empty($conclusionsDir) && is_dir($conclusionsDir)) {
            $files = array_slice(scandir($conclusionsDir), 2);
            foreach ($files as $file) {
                if (str_starts_with($file, 'nb_')) {
                    continue;
                }
                try {
                    $path = Info::CONC_FOLDER . '\\' . $file;
                    if (is_file($path) && (GrammarHandler::endsWith($file, '.pdf') || GrammarHandler::endsWith($file, '.doc') || GrammarHandler::endsWith($file, '.docx'))) {
                        // обрабатываю файлы .pdf .doc .docx
                        // проверю, зарегистрирован ли файл
                        if (array_key_exists($file, $availData)) {
                            // если файл уже зарегистрирован-проверю, если он не менялся-пропущу его, иначе-обновлю информацию
                            $existentFile = $availData[$file];
                            $stat = stat($path);
                            $changeTime = $stat['mtime'];
                            if ($existentFile->file_create_time === $changeTime) {
                                continue;
                            }
                            echo "refresh file info\n";
                            // иначе - обновлю информацию о файле
                            FileUtils::handleFileUpload($path);
                            $stat = stat($path);
                            $changeTime = $stat['mtime'];
                            $existentFile->file_create_time = $changeTime;
                            $existentFile->save();
                        } else {
                            // иначе-отправляю файл на обработку
                            $newFilePath = FileUtils::handleFileUpload($path);
                            if ($newFilePath !== $path) {
                                unlink($path);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    echo 'ERROR CHECKING FILE' . $e->getMessage() . "\n";
                }
            }
        }
        // получу список того, что ожидает отправки
        $waitForNotify = NotificationSendingInfo::getWaiting();
        if (!empty($waitForNotify)) {
            foreach ($waitForNotify as $waiting) {
                if ($waiting->create_time < time() - 900) {
                    // отправлю уведомление о том, что добавлен новый компонтент
                    $waiting->notify();
                }
            }
        }
    }

    /**
     * @param $name
     * @return string
     * @throws Exception
     * @throws \Exception
     */
    public static function createUser($name): string
    {
        $new = new User();
        $password = User::generateNumericPassword();
        $hash = Yii::$app->getSecurity()->generatePasswordHash($password);
        $auth_key = Yii::$app->getSecurity()->generateRandomString();
        $new->username = $name;
        $new->auth_key = $auth_key;
        $new->access_token = Yii::$app->getSecurity()->generateRandomString(255);
        $new->password_hash = $hash;
        $new->status = 1;
        $new->created_at = time();
        $new->updated_at = time();
        $new->save();
        // выдам пользователю права на чтение
        $auth = Yii::$app->authManager;
        if ($auth !== null) {
            $readerRole = $auth->getRole('reader');
            $auth->assign($readerRole, $new->getId());
            return $password;
        }
// Добавлю вручную
        (new AuthAssignment(['user_id' => $new->id, 'item_name' => 'reader', 'created_at' => time()]))->save();
        return $password;
    }

    /**
     * @param $executionNumber
     * @param string $executionDir
     * @throws Exception
     */
    public static function packFiles($executionNumber, string $executionDir): void
    {
// скопирую в папку содержимое dicom-просмотрщика
        $viewer_dir = Info::DICOM_VIEWER_FOLDER;
        self::recurse_copy($viewer_dir, $executionDir);
        $fileWay = Info::EXEC_FOLDER . '\\' . $executionNumber . '_tmp.zip';
        $trueFileWay = Info::EXEC_FOLDER . '\\' . GrammarHandler::toLatin($executionNumber) . '.zip';
        // создам архив и удалю исходное
        shell_exec('cd /d ' . $executionDir . ' && "' . Info::WINRAR_FOLDER . '"  a -afzip -r -df  ' . $fileWay . ' .');
        // удалю пустую директорию
        // переименую файл
        rename($fileWay, $trueFileWay);
        rmdir($executionDir);
        // проверю, зарегистрирован ли пациент
        $user = User::findByUsername($executionNumber);
        if ($user === null) {
            // регистрирую
            self::checkUser($executionNumber);
            $user = User::findByUsername($executionNumber);
        }
        // теперь проверю, зарегистрирован ли файл
        $registeredFile = Table_availability::findOne(['is_execution' => 1, 'userId' => $executionNumber]);
        if ($registeredFile === null) {
            // регистрирую файл
            $md5 = md5_file($trueFileWay);
            $item = new Table_availability(['file_name' => GrammarHandler::toLatin($executionNumber) . '.zip', 'is_execution' => true, 'md5' => $md5, 'file_create_time' => time(), 'userId' => $user->username, 'execution_type' => self::getExecutionType(GrammarHandler::toLatin($executionNumber))]);
            $item->save();
            Telegram::sendExecutionAdded($item);
        }
    }

    /**
     * @param $name
     * @throws Exception
     */
    private static function checkUser($name): void
    {
// проверю, зарегистрирован ли пользователь с данным именем. Если нет- зарегистрирую
        $user = User::findByUsername($name);
        if ($user === null) {
            $transaction = new DbTransaction();
            self::createUser($name);
            $transaction->commitTransaction();
        }
    }

    /**
     * @param int $id
     * @param string|null $subscriberId
     * @throws Exception
     */
    public static function checkAvailabilityForBots(int $id, string $subscriberId = null): void
    {
        // получу обследование
        $execution = User::findIdentity($id);
        if ($execution !== null) {
            // сначала получу аккаунты, которые подписаны на это обследование
            $subscribers = ViberSubscriptions::findAll(['patient_id' => $id]);
            if (!empty($subscribers)) {
                // проверю наличие заключений и файлов обследования
                $existentFile = Table_availability::findOne(['is_execution' => true, 'userId' => $execution->username]);
                if ($existentFile !== null) {
                    $link = TempDownloadLinks::createLink(
                        $execution,
                        'execution',
                        $existentFile->file_name
                    );
                    if ($link !== null) {
                        Viber::sendTempLink($subscriberId, $link->link);
                    }
                }
                // получу все доступные заключения
                $existentConclusions = Table_availability::findAll(['is_conclusion' => 1, 'userId' => $execution->username]);
                if ($existentConclusions !== null) {
                    foreach ($existentConclusions as $existentConclusion) {
                        $link = TempDownloadLinks::createLink(
                            $execution,
                            'conclusion',
                            $existentConclusion->file_name
                        );
                        if ($link !== null) {
                            Viber::sendTempLink($subscriberId, $link->link);
                        }
                    }
                }
            }
        }
    }

    /**
     * Посчитаю количество загруженных заключений
     * @param string $username <p>Номер обследования</p>
     * @return int
     */
    public static function countConclusions(string $username): int
    {
        $conclusionsCount = 0;
        // посчитаю заключения по конкретному обследованию
        if(is_dir(Info::CONC_FOLDER)){
            $entities = array_slice(scandir(Info::CONC_FOLDER), 2);
            $pattern = '/^' . $username . '-\d.+pdf$/';
            foreach ($entities as $entity) {
                if ($entity === $username . '.pdf' || preg_match($pattern, $entity)) {
                    $conclusionsCount++;
                }
            }
        }
        return $conclusionsCount;
    }

    /**
     * @throws StaleObjectException
     * @throws Throwable
     */
    public static function deleteAllConclusions($executionNumber): void
    {
        // также удалю информацию о доступности заключений из таблицы
        $avail = Table_availability::findAll(['userId' => $executionNumber, 'is_conclusion' => 1]);
        if ($avail !== null) {
            foreach ($avail as $item) {
                $conclusionFile = Info::CONC_FOLDER . '\\' . $item->file_name;
                if (is_file($conclusionFile)) {
                    unlink($conclusionFile);
                }
                $item->delete();
            }
        }
    }

    /**
     * @param $center
     * @return array
     * @throws Exception
     */
    #[ArrayShape(['status' => "int", 'message' => "string"])] public static function registerNext($center): array
    {
        // сначала получу последнего зарегистрированного
        $previousRegged = User::getLast($center);
        // найду первый свободный номер после последнего зарегистрированного
        $executionNumber = $previousRegged;
        while (true) {
            $executionNumber = User::getNext($executionNumber);
            if (User::findByUsername($executionNumber) === null) {
                break;
            }
        }
        $pass = self::createUser($executionNumber);
        return ['status' => 1, 'message' => ' <h2 class="text-center">Обследование №' . $executionNumber . '  зарегистрировано.</h2> Пароль для пациента: <b class="text-success">' . $pass . '</b> <button class="btn btn-default" id="copyPassBtn" data-password="' . $pass . '"><span class="text-success">Копировать пароль</span></button><script>const copyBtn = $("button#copyPassBtn");copyBtn.on("click.copy", function (){copyPass.call(this)});copyBtn.focus()</script>'];
    }

    public static function getConclusionText(string $username): string
    {
        $answer = '';
        $avail = Table_availability::find()->where(['is_conclusion' => 1, 'userId' => $username])->all();
        if (!empty($avail)) {
            foreach ($avail as $item) {
                $answer .= "$item->file_name ";
            }
        }
        return $answer;
    }

    /**
     * @throws Exception
     */
    public static function getExecutionsList(User $user): array
    {
        $answer = [];
        // find all user executions
        $available = Table_availability::findAll(['userId' => $user->username]);
        // file items
        $files = [];
        $conclusionsCount = 0;
        $executionsCount = 0;
        $answerItem = [];
        foreach ($available as $item) {
            $file = [];
            $file['fileName'] = $item->file_name;
            $file['link'] = TempDownloadLinks::getLink($item, $user);
            if (!empty($item->execution_date)) {
                $answerItem['executionDate'] = TimeHandler::timestampToDate($item->execution_date);
            }
            if ($item->is_conclusion) {
                $conclusionsCount++;
                $file['type'] = 'pdf';
                if (!empty($item->patient_name)) {
                    $answerItem['patientName'] = $item->patient_name;
                }
                if (!empty($item->patient_birthdate)) {
                    $answerItem['patientBirthdate'] = $item->patient_birthdate;
                }
                if (!empty($item->execution_area)) {
                    $file['executionArea'] = $item->execution_area;
                    $file['name'] = 'Заключение врача: ' . $item->execution_area;
                    $file['serviceName'] = "$item->userId {$file['name']}.pdf";
                    if (mb_strlen($file['name']) > 255) {
                        $file['name'] = mb_substr($file['name'], 0, 252) . '...';
                    }
                } else {
                    $file['name'] = 'Заключение врача';
                }
            } else {
                $executionsCount++;
                $file['type'] = 'zip';
                $file['name'] = 'Архив обследования';
                $file['serviceName'] = "$item->userId {$file['name']}.zip";
            }
            $file['executionId'] = $user->username;
            $files[] = $file;
        }
        $answerItem['executionId'] = $user->username;
        $answerItem['files'] = $files;
        $answerItem['executionDate'] = TimeHandler::timestampToDate($user->created_at);
        $answerItem['executionType'] = self::getExecutionType($user->username);
        $answerItem['type'] = self::EXECUTION_TYPE_CURRENT;
        $answerItem['conclusions'] = $conclusionsCount;
        $answerItem['executions'] = $executionsCount;
        $answer[$user->username] = $answerItem;
        // search archive values if exists

        if (!empty($answerItem['patientName']) && !empty($answerItem['patientBirthdate'])) {
            $archiveExecutions = Archive_complex_execution_info::find()->where(['patient_name' => $answerItem['patientName'], 'patient_birthdate' => $answerItem['patientBirthdate']])->all();
            if (!empty($archiveExecutions)) {
                foreach ($archiveExecutions as $archiveExecution) {
                    $answer[$archiveExecution->execution_number]['type'] = self::EXECUTION_TYPE_ARCHIVE;
                    if (empty($answer[$archiveExecution->execution_number]['conclusions'])) {
                        $answer[$archiveExecution->execution_number]['conclusions'] = 1;
                    } else {
                        ++$answer[$archiveExecution->execution_number]['conclusions'];
                    }
                    $answerItem['executions'] = 0;
                    $answer[$archiveExecution->execution_number]['patientName'] = $archiveExecution->patient_name;
                    $answer[$archiveExecution->execution_number]['executionId'] = $archiveExecution->execution_number;
                    $answer[$archiveExecution->execution_number]['patientBirthdate'] = $archiveExecution->patient_birthdate;
                    $answer[$archiveExecution->execution_number]['executionType'] = self::getExecutionType($archiveExecution->execution_number);
                    $answer[$archiveExecution->execution_number]['executionDate'] = TimeHandler::archiveDateToDate($archiveExecution->execution_date);
                    $answer[$archiveExecution->execution_number]['files'][] = [
                        'executionId' => $archiveExecution->execution_number,
                        'name' => 'Заключение врача: ' . $archiveExecution->execution_area,
                        'serviceName' => $archiveExecution->execution_number . ' Заключение врача: ' . $archiveExecution->execution_area . '.pdf',
                        'fileName' => $archiveExecution->pdf_path,
                        'executionArea' => $archiveExecution->execution_area,
                        'type' => 'pdf',
                        'link' => TempDownloadLinks::getArchiveFileLink($archiveExecution, $user->id),
                        'archivePdfName' => $archiveExecution->execution_identifier
                    ];
                }
            }
        }
        return array_values($answer);
    }

    #[Pure] public static function getExecutionInfo(User $user): ExecutionInfo
    {
        $info = new ExecutionInfo();
        $info->executionId = $user->username;
        $info->executionType = self::getExecutionType($info->executionId);
        $info->executionDate = $user->created_at;
        return $info;
    }

    public static function getTodayCenterInfo(): array
    {
        $answer = [];
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            foreach ($registeredToday as $item) {
                /** @var Emails $mailInfo */
                $person = [];
                $conclusionInfo = [];
                $person['executionId'] = $item->username;
                $mailInfo = Emails::findOne(['patient_id' => $item->id]);
                if ($mailInfo !== null) {
                    $person['hasMail'] = 'Да';
                    $person['isMailSent'] = $mailInfo->mailed_yet === 1 ? 'Да' : 'Нет';
                } else {
                    $person['hasMail'] = 'Нет';
                    $person['isMailSent'] = 'Нет';
                }
                // check files availability
                $person = self::getPerson($item, $person, $conclusionInfo);
                if (!empty($person['patientName'])) {
                    $person['previousExecutionsCount'] = (string)Archive_complex_execution_info::countPreviousExecutions($person['patientName'], $person['birthdate']);
                } else {
                    $person['previousExecutionsCount'] = "0";
                }
                $answer[] = $person;
            }
        }
        return $answer;
    }

    /**
     * @throws \Exception
     */
    public static function getWholeInfo(): array
    {
        $answer = [];
        $registered = User::findAllRegistered();
        foreach ($registered as $item) {
            $person = [];
            /** @var Emails $mailInfo */
            $mailInfo = Emails::findOne(['patient_id' => $item->id]);
            $person['hasMail'] = 'Да';
            if ($mailInfo !== null) {
                $person['isMailSent'] = $mailInfo->mailed_yet ? 'Да' : 'Нет';
            } else {
                $person['isMailSent'] = 'Нет';
            }
            $conclusionInfo = [];
            $person['executionId'] = $item->username;
            // check files availability
            $person = self::getPerson($item, $person, $conclusionInfo);
            $person['executionDate'] = $item->created_at;
            if (!empty($person['patientName'])) {
                $person['previousExecutionsCount'] = (string)Archive_complex_execution_info::countPreviousExecutions($person['patientName'], $person['birthdate']);
            } else {
                $person['previousExecutionsCount'] = "0";
            }
            $answer[] = $person;
        }
        return $answer;
    }

    /**
     * @param mixed $item
     * @param array $person
     * @param array $conclusionInfo
     * @return array
     */
    public static function getPerson(mixed $item, array $person, array $conclusionInfo): array
    {
        $existentFiles = Table_availability::findAll(['userId' => $item->username]);
        if (!empty($existentFiles)) {
            foreach ($existentFiles as $existentFile) {
                if ($existentFile->is_execution) {
                    $person['haveExecution'] = 1;
                } else {
                    if (!empty($existentFile->patient_name)) {
                        $person['patientName'] = $existentFile->patient_name;
                        $person['birthdate'] = $existentFile->patient_birthdate;
                        $person['contrast'] = $existentFile->contrast_info;
                        $person['doctor'] = $existentFile->diagnostitian;
                    }
                    $conclusionInfo[$existentFile->file_name] = $existentFile->execution_area;
                }
            }
        }
        $person['conclusionInfo'] = $conclusionInfo;
        return $person;
    }

    public static function getTodayStatistics(int $center): string
    {
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            $currentCenterPersons = [];
            foreach ($registeredToday as $item) {
                if ($center === self::CENTER_AURORA && str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                } else if ($center === self::CENTER_NV && !str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                }
            }
            if (!empty($currentCenterPersons)) {
                $statistics = 'Зарегистрированных обследований за день: ' . count($currentCenterPersons) . "\n";
                $withoutExecutions = 0;
                $withoutConclusions = 0;
                /** @var User $person */
                foreach ($currentCenterPersons as $person) {
                    if (!Table_availability::isConclusion($person)) {
                        $withoutConclusions++;
                    }
                    if (!Table_availability::isExecution($person)) {
                        $withoutExecutions++;
                    }
                }
                if ($withoutExecutions > 0) {
                    $statistics .= "Без архива DICOM: $withoutExecutions. Подробный список: /today_no_dicom_$center\n";
                }
                if ($withoutConclusions > 0) {
                    $statistics .= "Без заключений: $withoutExecutions. Подробный список: /today_no_conclusion_$center\n";
                }
                $statistics .= "Список обследований: /today_list_$center\n";
            } else {
                $statistics = 'Сегодня обследований ещё не было';
            }
        } else {
            $statistics = 'Сегодня обследований ещё не было';
        }
        return $statistics;
    }

    public static function getTodayExecutionList(string $centerStr): string
    {
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            $center = (int)$centerStr;
            $currentCenterPersons = [];
            foreach ($registeredToday as $item) {
                if ($center === self::CENTER_AURORA && str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                } else if ($center === self::CENTER_NV && !str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                }
            }
            if (!empty($currentCenterPersons)) {
                $statistics = 'Зарегистрированных обследований за день: ' . count($currentCenterPersons) . "\n";
                /** @var User $person */
                foreach ($currentCenterPersons as $person) {
                    $statistics .= "$person->username\n";
                    $statistics .= "/qr_$person->username\n";
                    $statistics .= (Table_availability::getPatientName($person->username) ?? "Имя ещё не назначено") . "\n";
                    $statistics .= (Table_availability::getConclusionAreas($person->username) ?? "Нет заключения\n");
                    if (!Table_availability::isExecution($person)) {
                        $statistics .= "Нет DICOM\n";
                    }
                    $statistics .= "===================\n";
                }
            } else {
                $statistics = 'Сегодня обследований ещё не было';
            }
        } else {
            $statistics = 'Сегодня обследований ещё не было';
        }
        return $statistics;
    }

    public static function getTodayNoConclusion(string $centerStr): string
    {
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            $center = (int)$centerStr;
            $currentCenterPersons = [];
            foreach ($registeredToday as $item) {
                if ($center === self::CENTER_AURORA && str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                } else if ($center === self::CENTER_NV && !str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                }
            }
            if (!empty($currentCenterPersons)) {
                $statistics = "Заключения отсутствуют:\n";
                /** @var User $person */
                foreach ($currentCenterPersons as $person) {
                    if (!Table_availability::isConclusion($person)) {
                        $statistics .= "$person->username\n";
                    }
                }
                if ($statistics === "Заключения отсутствуют:\n") {
                    $statistics = 'Кажется, все заключения загружены';
                }
            } else {
                $statistics = 'Кажется, все заключения загружены';
            }
        } else {
            $statistics = 'Обследований за сегодня не зарегистрировано';
        }
        return $statistics;
    }

    public static function getTodayNoExecution(string $centerStr): string
    {
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            $center = (int)$centerStr;
            $currentCenterPersons = [];
            foreach ($registeredToday as $item) {
                if ($center === self::CENTER_AURORA && str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                } else if ($center === self::CENTER_NV && !str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                }
            }
            if (!empty($currentCenterPersons)) {
                $statistics = "Снимки отсутствуют:\n";
                /** @var User $person */
                foreach ($currentCenterPersons as $person) {
                    if (!Table_availability::isExecution($person)) {
                        $statistics .= "$person->username\n";
                    }
                }
                if ($statistics === "Снимки отсутствуют:\n") {
                    $statistics = 'Кажется, все снимки загружены';
                }
            } else {
                $statistics = 'Кажется, все снимки загружены';
            }
        } else {
            $statistics = 'Обследований за сегодня не зарегистрировано';
        }
        return $statistics;
    }

    #[ArrayShape([self::SCENARIO_ADD => "string[]"])] public function scenarios(): array
    {
        return [
            self::SCENARIO_ADD => ['executionNumber'],
        ];
    }

    public ?string $executionNumber = null;

    #[ArrayShape(['executionNumber' => "string"])] public function attributeLabels(): array
    {
        return [
            'executionNumber' => 'Номер обследования',
        ];
    }

    public function rules(): array
    {
        return [
            [['executionNumber'], 'required', 'on' => self::SCENARIO_ADD],
            ['executionNumber', 'string', 'length' => [1, 255]],
            ['executionNumber', 'match', 'pattern' => '/^[а-яa-z0-9]+$/iu']
        ];
    }

    /**
     * @return array|null
     * @throws Exception
     * @throws \yii\db\Exception
     */
    #[ArrayShape(['status' => "int", 'message' => "string"])] public function register(): ?array
    {
        if ($this->validate()) {
            $transaction = new DbTransaction();
            if (empty($this->executionNumber)) {
                return ['status' => 2, 'message' => 'Не указан номер обследования'];
            }
            $this->executionNumber = GrammarHandler::toLatin($this->executionNumber);
            // проверю, не зарегистрировано ли уже обследование
            if (User::findByUsername($this->executionNumber) !== null) {
                return ['status' => 4, 'message' => 'Это обследование уже зарегистрировано, вы можете изменить информацию о нём в списке'];
            }
            $password = self::createUser($this->executionNumber);
            // отмечу, что добавлены файлы обследования
            $transaction->commitTransaction();
            return ['status' => 1, 'message' => ' <h2 class="text-center">Обследование №' . $this->executionNumber . '  зарегистрировано.</h2> Пароль для пациента: <b class="text-success">' . $password . '</b> <button class="btn btn-default" id="copyPassBtn" data-password="' . $password . '"><span class="text-success">Копировать пароль</span></button>'];
        }
        die('error');
    }

    /**
     * Проверю наличие файлов
     * @param $name
     * @return bool
     */
    public static function isExecution($name): bool
    {
        $filename = Yii::getAlias('@executionsDirectory') . '\\' . $name . '.zip';
        if (is_file($filename)) {
            return true;
        }
        return false;
    }

    /**
     * Проверю наличие заключения
     * @param $name
     * @return bool
     */
    public static function isConclusion($name): bool
    {
        $filename = Info::CONC_FOLDER . '\\' . $name . '.pdf';
        if (is_file($filename)) {
            return true;
        }
        return false;
    }

    public static function recurse_copy($src, $dst): void
    {
        $dir = opendir($src);
        if (!is_dir($dst) && !mkdir($dst) && !is_dir($dst)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dst));
        }
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::recurse_copy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }


    public static function countWithoutConclusionsToday(int $center): int
    {
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            $currentCenterPersons = [];
            foreach ($registeredToday as $item) {
                if ($center === self::CENTER_AURORA && str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                } else if ($center === self::CENTER_NV && !str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                }
            }
            if (!empty($currentCenterPersons)) {
                $withoutConclusions = 0;
                /** @var User $person */
                foreach ($currentCenterPersons as $person) {
                    if (!Table_availability::isConclusion($person)) {
                        $withoutConclusions++;
                    }
                }
                return $withoutConclusions;
            }
        }
        return 0;
    }

    public static function countWithoutExecutionsToday(int $center): int
    {
        $dayStart = TimeHandler::getTodayStart();
        // get executions registered for this center for today
        $registeredToday = User::find()->where(['>', 'created_at', $dayStart])->all();
        if (!empty($registeredToday)) {
            $currentCenterPersons = [];
            foreach ($registeredToday as $item) {
                if ($center === self::CENTER_AURORA && str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                } else if ($center === self::CENTER_NV && !str_starts_with($item->username, "A")) {
                    $currentCenterPersons[] = $item;
                }
            }
            if (!empty($currentCenterPersons)) {
                $withoutExecutions = 0;
                /** @var User $person */
                foreach ($currentCenterPersons as $person) {
                    if (!Table_availability::isExecution($person)) {
                        $withoutExecutions++;
                    }
                }
                return $withoutExecutions;
            }
        }
        return 0;
    }

    private static function getExecutionType(string $id): string
    {
        if (str_starts_with($id, "T")) {
            return "КТ";
        }
        return 'МРТ';
    }
}