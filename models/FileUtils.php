<?php


namespace app\models;


use app\models\database\Archive_complex_execution_info;
use app\models\utils\FirebaseHandler;
use app\models\utils\GrammarHandler;
use app\priv\Info;
use Exception;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfReader\PdfReaderException;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use ZipArchive;

class FileUtils
{
    public const FOLDER_WAITING_TIME = 300;

    /**
     * Метод проверяет нераспознанные директории файлов и возвращает список нераспознанных
     * На случай ошибок администраторов в обзывании папок
     * @return array
     */
    public static function checkUnhandledFolders(): array
    {
        // это список нераспознанных папок
        $unhandledFoldersList = [];
        if (is_dir(Info::EXEC_FOLDER)) {
            // паттерн валидных папок
            $pattern = '/^[aа]?\d+$/ui';
            // получу список папок с заключениями
            $dirs = array_slice(scandir(Info::EXEC_FOLDER), 2);
            foreach ($dirs as $dir) {
                $path = Yii::getAlias('@executionsDirectory') . '/' . $dir;
                if (is_dir($path)) {
                    // если папка не соответствует принятому названию- внесу её в список нераспознанных
                    // отфильтрую свежесозданные папки: они могут быть ещё в обработке
                    $stat = stat($path);
                    $changeTime = $stat['mtime'];
                    $difference = time() - $changeTime;
                    if ($difference > self::FOLDER_WAITING_TIME && !preg_match($pattern, $dir)) {
                        $unhandledFoldersList[] = $dir;
                    }
                }
            }
        }
        return $unhandledFoldersList;
    }

    public static function deleteUnhandledFolder(): void
    {
        // получу имя папки
        $folderName = Yii::$app->request->post('folderName');
        if (!empty($folderName)) {
            $path = Yii::getAlias('@executionsDirectory') . '/' . $folderName;
            if (is_dir($path)) {
                self::removeDir($path);
            }
        }
    }

    public static function removeDir($path): bool
    {
        if (is_file($path)) {
            return unlink($path);
        }
        if (is_dir($path)) {
            foreach (scandir($path, SCANDIR_SORT_NONE) as $p) {
                if (($p !== '.') && ($p !== '..')) {
                    self::removeDir($path . DIRECTORY_SEPARATOR . $p);
                }
            }
            return rmdir($path);
        }
        return false;
    }

    public static function renameUnhandledFolder(): void
    {
        $oldFolderName = Yii::$app->request->post('oldName');
        $newFolderName = Yii::$app->request->post('newName');
        if (!empty($oldFolderName)) {
            $path = Yii::getAlias('@executionsDirectory') . '/' . $oldFolderName;
            if (is_dir($path)) {
                rename($path, Yii::getAlias('@executionsDirectory') . '\\' . $newFolderName);
            }
        }

    }

    /**
     * Получение списка папок, ожидающих обработки
     * @return array <p>Возвращает список имён папок</p>
     */
    public static function checkWaitingFolders(): array
    {
        // это список ожидающих папок
        $waitingFoldersList = [];
        if (is_dir(Yii::getAlias('@executionsDirectory'))) {
            // паттерн валидных папок
            $pattern = '/^[aа]?\d+$/ui';
            // получу список папок с заключениями
            $dirs = array_slice(scandir(Yii::getAlias('@executionsDirectory')), 2);
            foreach ($dirs as $dir) {
                $path = Yii::getAlias('@executionsDirectory') . '/' . $dir;
                // если папка не соответствует принятому названию- внесу её в список нераспознанных
                // отфильтрую свежесозданные папки: они могут быть ещё в обработке
                if (is_dir($path) && preg_match($pattern, $dir)) {
                    $waitingFoldersList[] = $dir;
                }
            }
        }
        return $waitingFoldersList;
    }

    /**
     * @return string
     */
    public static function getUpdateInfo(): string
    {
        $file = Yii::$app->basePath . '\\logs\\update.log';
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return 'file is empty';
    }

    /**
     * @return string
     */
    public static function getJavaInfo(): string
    {
        $file = Yii::$app->basePath . '\\logs\\java_info_error.log';
        if (is_file($file)) {
            return GrammarHandler::convertToUTF(file_get_contents($file));
        }
        return 'file is empty';
    }

    /**
     * @return string
     */
    public static function getOutputInfo(): string
    {
        $file = Yii::$app->basePath . '\\logs\\content_change.log';
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return 'file is empty';
    }

    /**
     * @return string
     */
    public static function getErrorInfo(): string
    {
        $file = Yii::$app->basePath . '\\logs\\content_change_err.log';
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return 'file is empty';
    }

    public static function setUpdateInProgress(): void
    {
        $file = Yii::$app->basePath . '\\priv\\update_progress.conf';
        file_put_contents($file, '1');
    }

    public static function setUpdateFinished(): void
    {
        $file = Yii::$app->basePath . '\\priv\\update_progress.conf';
        file_put_contents($file, '0');
    }

    public static function isUpdateInProgress(): bool
    {
        $file = Yii::$app->basePath . '\\priv\\update_progress.conf';
        if (is_file($file)) {
            $content = file_get_contents($file);
            if ($content) {
                // проверю, что с момента последнего обновления прошло не больше 15 минут. Если больше- сброшу флаг
                $lastTime = self::getLastUpdateTime();
                return !(time() - $lastTime > 900);
            }
            return false;
        }
        return false;
    }

    public static function setLastUpdateTime(): void
    {
        $file = Yii::$app->basePath . '\\priv\\last_update_time.conf';
        file_put_contents($file, time());
    }

    public static function getLastUpdateTime(): int
    {
        $file = Yii::$app->basePath . '\\priv\\last_update_time.conf';
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return 0;
    }

    /**
     * @param $text
     */
    public static function writeUpdateLog($text): void
    {
        try {
            $logPath = Yii::$app->basePath . '\\logs\\update.log';
            $newContent = $text . "\n";
            if (is_file($logPath)) {
                // проверю размер лога
                $content = file_get_contents($logPath);
                if (!empty($content) && $content !== '') {
                    $notes = mb_split("\n", $content);
                    if (!empty($notes) && count($notes) > 0) {
                        $notesCounter = 0;
                        foreach ($notes as $note) {
                            if ($notesCounter > 30) {
                                break;
                            }
                            $newContent .= $note . "\n";
                            ++$notesCounter;
                        }
                    }
                }
            }
            file_put_contents($logPath, $newContent);
        } catch (Exception) {
        }
    }

    public static function getServiceErrorsInfo(): bool|string
    {
        $logPath = Yii::$app->basePath . '\\errors\\errors.txt';
        if (is_file($logPath)) {
            return file_get_contents($logPath);
        }
        return 'no errors';
    }

    public static function getUpdateOutputInfo(): bool|string
    {
        $outFilePath = Yii::$app->basePath . '\\logs\\update_file.log';
        if (is_file($outFilePath)) {
            return file_get_contents($outFilePath);
        }
        return 'no info';
    }

    public static function getUpdateErrorInfo(): bool|string
    {

        $outFilePath = Yii::$app->basePath . '\\logs\\update_err.log';
        if (is_file($outFilePath)) {
            return file_get_contents($outFilePath);
        }
        return 'no errors';
    }

    public static function setLastCheckUpdateTime(): void
    {
        $file = Yii::$app->basePath . '\\priv\\last_check_update_time.conf';
        file_put_contents($file, time());
    }

    public static function getLastCheckUpdateTime(): int
    {
        $file = Yii::$app->basePath . '\\priv\\last_check_update_time.conf';
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return 0;
    }

    public static function addBackgroundToPDF($file, $fileName): void
    {
        // сохраню копию файла без фона
        self::copyWithoutBackground($file, $fileName);
        if (str_starts_with($fileName, "T")) {
            $pdfBackgroundImage = Yii::$app->basePath . '\\design\\back_ct.jpg';
        } else {
            $pdfBackgroundImage = Yii::$app->basePath . '\\design\\back.jpg';
        }
        if (is_file($file) && is_file($pdfBackgroundImage)) {
            $pdf = new Fpdi();

            $pdf->AddPage();

            $pdf->Image($pdfBackgroundImage, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
            try {
                $pdf->setSourceFile($file);
                $tplIdx = $pdf->importPage(1);
                $pageInfo = $pdf->getTemplateSize($tplIdx);
                $pdf->useTemplate($tplIdx, 0, 0, $pageInfo['width'], $pageInfo['height'], true);

                $pageCounter = 2;
                // попробую добавить оставшиеся страницы
                while (true) {
                    try {
                        $tplIdx = $pdf->importPage($pageCounter);
                        $pageInfo = $pdf->getTemplateSize($tplIdx);
                        $pdf->AddPage();
                        $pdf->useTemplate($tplIdx, 0, 0, $pageInfo['width'], $pageInfo['height'], true);
                        ++$pageCounter;
                    } catch (Exception) {
                        break;
                    }
                }
                $tempFileName = $file . '_tmp';
                $pdf->Output($tempFileName, 'F');
                unlink($file);
                rename($tempFileName, $file);
            } catch (PdfParserException | PdfReaderException) {
            }
        }
    }

    /**
     * Просто добавлю фон к файлу
     * @param string $file
     * @param bool $isCt
     * @param bool $deleteOrigin
     * @return string|null
     */
    public static function addBackgroundToPdfSimple(string $file, bool $isCt = false, bool $deleteOrigin = true): ?string
    {
        if ($isCt) {
            $pdfBackgroundImage = Yii::$app->basePath . '\\design\\back_ct.jpg';
        } else {
            $pdfBackgroundImage = Yii::$app->basePath . '\\design\\back.jpg';
        }
        if (is_file($file) && is_file($pdfBackgroundImage)) {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->Image($pdfBackgroundImage, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
            try {
                $pdf->setSourceFile($file);
                $tplIdx = $pdf->importPage(1);
                $pdf->useTemplate($tplIdx, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight(), true);

                $pageCounter = 2;
                // попробую добавить оставшиеся страницы
                while (true) {
                    try {
                        $tplIdx = $pdf->importPage($pageCounter);
                        $pdf->AddPage();
                        $pageInfo = $pdf->getTemplateSize($tplIdx);
                        $pdf->useTemplate($tplIdx, 0, 0, $pageInfo['width'], $pageInfo['height'], true);
                        ++$pageCounter;
                    } catch (Exception) {
                        break;
                    }
                }

                $root = Yii::$app->basePath;
                // создам временную папку, если её ещё не существует
                if (!is_dir($root . '/temp') && !mkdir($concurrentDirectory = $root . '/temp') && !is_dir($concurrentDirectory)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
                $fileName = $root . "/temp/" . Yii::$app->security->generateRandomString() . ".pdf";
                $pdf->Output($fileName, 'F');
                if ($deleteOrigin) {
                    unlink($file);
                }
                return $fileName;
            } catch (PdfParserException | PdfReaderException | \yii\base\Exception) {
            }
        }
        return null;
    }

    /**
     * @param string $loadedFile
     * @return array|null
     */
    public static function handleLoadedFile(string $loadedFile): ?array
    {
        $existentJavaPath = 'java';
        $result = null;
        // проверю наличие обработчика
        $handler = Yii::$app->basePath . '\\java\\docx_to_pdf_converter.jar';
        $conclusionsDir = Info::CONC_FOLDER;
        if (is_file($handler) && is_file($loadedFile) && is_dir($conclusionsDir)) {
            $command = "\"$existentJavaPath\" -jar $handler \"$loadedFile\" \"$conclusionsDir\"";
            exec($command, $result);
            if (!empty($result)) {
                if (count($result) === 7) {
                    // получу вторую строку результата
                    $fileName = GrammarHandler::toLatin($result[1]);
                    if (str_ends_with($fileName, '.pdf')) {
                        return [
                            'filename' => $fileName,
                            'action_status' => GrammarHandler::convertToUTF($result[0]),
                            'execution_area' => GrammarHandler::convertToUTF($result[2]),
                            'patient_name' => GrammarHandler::convertToUTF($result[3]),
                            'birthdate' => GrammarHandler::convertToUTF($result[4]),
                            'doctor' => GrammarHandler::convertToUTF($result[5]),
                            'contrast' => GrammarHandler::convertToUTF($result[6])
                        ];
                    }
                } elseif (count($result) === 1) {
                    Telegram::sendDebug("Результат " . GrammarHandler::convertToUTF($result[0]) . " при выполнении команды $command");
                    Telegram::sendDebugFile($loadedFile);
                    unlink($loadedFile);
                    return ['action_status' => GrammarHandler::convertToUTF($result[0])];
                } elseif (count($result) === 2) {
                    Telegram::sendDebug("Ошибка " . GrammarHandler::convertToUTF($result[1]) . " при выполнении команды $command");
                    Telegram::sendDebugFile($loadedFile);
                    unlink($loadedFile);
                    return ['action_status' => GrammarHandler::convertToUTF($result[0])];
                } else {
                    Telegram::sendDebug("Результат " . GrammarHandler::convertToUTF(serialize($result)) . " при выполнении команды $command");
                    Telegram::sendDebugFile($loadedFile);
                    unlink($loadedFile);
                    return ['action_status' => GrammarHandler::convertToUTF(serialize($result))];
                }
            } else {
                Telegram::sendDebug("Пустой результат выполнения команды $command");
                Telegram::sendDebugFile($loadedFile);
                unlink($loadedFile);
            }
        } else {
            Telegram::sendDebug("no java handler");
        }
        return null;
    }

    public static function archiveFile(string $file): ?array
    {
        $result = null;
        $existentJavaPath = 'java';
        // проверю наличие обработчика
        $handler = Yii::$app->basePath . '\\java\\pdf_archiver.jar';
        $archiveDir = Info::ARCHIVE_PATH;
        if (is_file($handler) && is_file($file) && is_dir($archiveDir)) {
            Telegram::sendDebug("archive $file");
            $command = "\"$existentJavaPath\" -jar $handler \"$file\" \"$archiveDir\"";
            exec($command, $result);
            if (!empty($result)) {
                $stateLine = GrammarHandler::convertToUTF($result[0]);
                if (!str_starts_with($stateLine, "Архивировано")) {
                    Telegram::sendDebug("Результат " . GrammarHandler::convertToUTF(serialize($result)) . " при выполнении команды $command");
                    Telegram::sendDebugFile($file);
                }
                return $result;
            }
            Telegram::sendDebug("error archive file with $command");
            Telegram::sendDebugFile($file);
        }
        return null;
    }

    /**
     * @param string $downloadedFile
     * @param $extension
     * @return string
     * @throws \yii\base\Exception
     */
    public static function saveTempFile(string $downloadedFile, $extension): string
    {
        $root = Yii::$app->basePath;
        // создам временную папку, если её ещё не существует
        if (!is_dir($root . '/temp') && !mkdir($concurrentDirectory = $root . '/temp') && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        $fileName = Yii::$app->security->generateRandomString() . $extension;
        file_put_contents($root . "/temp/$fileName", $downloadedFile);
        return $root . "/temp/$fileName";
    }

    public static function saveScheduleFile(string $downloadedFile): void
    {
        $root = Yii::$app->basePath;
        // создам временную папку, если её ещё не существует
        if (!is_dir($root . '/schedule') && !mkdir($concurrentDirectory = $root . '/schedule') && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        $fileName = 'schedule.xlsx';
        file_put_contents($root . "/schedule/$fileName", $downloadedFile);
    }

    /**
     * @param $file
     * @return string
     * @throws \yii\base\Exception
     * @throws Exception
     */
    public static function handleFileUpload($file): string
    {
        // попробую обработать файл
        $actionResult = self::handleLoadedFile($file);
        if ($actionResult === null) {
            return 'Ошибка обработки файла, попробуйте позднее';
        }
        if (count($actionResult) === 1) {
            return 'Ошибка: ' . $actionResult['action_status'];
        }
        if (count($actionResult) === 7) {
            // добавлю фон заключению
            $conclusionFile = $actionResult['filename'];
            $path = Info::CONC_FOLDER . '\\' . $conclusionFile;
            if (is_file($path)) {
                self::addBackgroundToPDF($path, $conclusionFile);
                // если создан новый файл-зарегистрирую его доступность
                $user = User::findByUsername(GrammarHandler::getBaseFileName($conclusionFile));
                if ($user === null) {
                    // создам учётную запись
                    ExecutionHandler::createUser(GrammarHandler::getBaseFileName($conclusionFile));
                    $user = User::findByUsername(GrammarHandler::getBaseFileName($conclusionFile));
                    Telegram::sendDebug("Добавляю незарегистрированного пользователя. Номер обследования: $user->username, область обследования: {$actionResult['execution_area']}, фио: {$actionResult['patient_name']}");
                }
                $user = User::findByUsername(GrammarHandler::getBaseFileName($conclusionFile));
                if (!Table_availability::isRegistered($conclusionFile)) {
                    $md5 = md5_file($path);
                    $stat = stat($path);
                    $changeTime = $stat['mtime'];
                    $item = new Table_availability([
                        'file_name' => $conclusionFile,
                        'is_conclusion' => true,
                        'md5' => $md5,
                        'file_create_time' => $changeTime,
                        'userId' => $user->username,
                        'patient_name' => $actionResult['patient_name'],
                        'execution_area' => $actionResult['execution_area'],
                        'patient_birthdate' => GrammarHandler::prepareDateForDb($actionResult['birthdate']),
                        'diagnostitian' => $actionResult['doctor'],
                        'contrast_info' => $actionResult['contrast']
                    ]);
                    $item->save();
                    Telegram::notifyConclusionAdded($item);

                    FirebaseHandler::sendConclusionLoaded(
                        $user->id,
                        $conclusionFile,
                        false
                    );
                    // отправлю оповещение о добавленном контенте, если указан адрес почты
                    /*if (Emails::checkExistent($user->id)) {
                        Emails::sendEmail($item);
                    }*/
                } else {
                    $info = Table_availability::findOne(['file_name' => $conclusionFile]);
                    $md5 = md5_file($path);
                    $stat = stat($path);
                    $changeTime = $stat['mtime'];
                    if ($info !== null) {
                        $info->md5 = $md5;
                        $info->file_create_time = $changeTime;
                        $info->patient_name = $actionResult['patient_name'];
                        $info->execution_area = $actionResult['execution_area'];
                        $info->save();
                        FirebaseHandler::sendConclusionLoaded(
                            $user->id,
                            $conclusionFile,
                            true
                        );
                    }
                }
                return $path;
            }
        }
        return $actionResult['action_status'];
    }

    private static function copyWithoutBackground($file, $fileName): void
    {
        // получу новое имя файла
        $newFileName = 'nb_' . $fileName;
        copy($file, Info::CONC_FOLDER . '\\' . $newFileName);
    }

    public static function getSoftwareVersion(): bool|string|null
    {

        $versionFile = Yii::$app->basePath . '\\version.info';
        if (is_file($versionFile)) {
            return file_get_contents($versionFile);
        }
        return null;
    }

    public static function getLastTgMessage(): bool|string|null
    {

        $versionFile = Yii::$app->basePath . '\\logs\\last_tg_message.log';
        if (is_file($versionFile)) {
            return file_get_contents($versionFile);
        }
        return null;
    }

    public static function setTelegramLog(string $message): void
    {
        $versionFile = Yii::$app->basePath . '\\logs\\last_tg_state.log';
        file_put_contents($versionFile, $message);
    }

    public static function getLastTelegramLog(): bool|string|null
    {
        $versionFile = Yii::$app->basePath . '\\logs\\last_tg_state.log';
        if (is_file($versionFile)) {
            return file_get_contents($versionFile);
        }
        return null;
    }

    /**
     * @return bool
     */
    public static function isSoftwareVersionChanged(): bool
    {
        $oldVersionFile = Yii::$app->basePath . '\\old_version.info';
        if (!is_file($oldVersionFile)) {
            file_put_contents($oldVersionFile, '0');
        }
        if (self::getSoftwareVersion() !== file_get_contents($oldVersionFile)) {
            file_put_contents($oldVersionFile, self::getSoftwareVersion());
            return true;
        }
        return false;
    }

    /**
     * @param $fileName
     * @param User $user
     * @throws NotFoundHttpException
     */
    public static function loadFile($fileName, User $user): void
    {
        $file = Table_availability::findOne(['file_name' => $fileName]);
        if ($file !== null && $file->userId === $user->username) {
            if ($file->is_execution) {
                $path = Yii::getAlias('@executionsDirectory') . DIRECTORY_SEPARATOR . $fileName;
            } else {
                $path = Yii::getAlias('@conclusionsDirectory') . DIRECTORY_SEPARATOR . $fileName;
            }
            if (is_file($path)) {
                Yii::$app->response->sendFile($path, $fileName);
                Yii::$app->response->send();
                return;
            }
            throw new NotFoundHttpException("File not found");
        }
    }


    /**
     * @param int $archiveFileId
     * @throws NotFoundHttpException
     */
    public static function loadArchiveFile(int $archiveFileId): void
    {
        $file = Archive_complex_execution_info::findOne(['execution_identifier' => $archiveFileId]);
        if ($file !== null) {
            $path = Info::PDF_ARCHIVE_PATH . $file->pdf_path;
            if (is_file($path)) {
                // add background to file
                $fileWithBackground = self::addBackgroundToPdfSimple($path, false, false);
                Yii::$app->response->sendFile($fileWithBackground, 'Заключение_' . time() . '.pdf');
                unlink($fileWithBackground);
                Yii::$app->response->send();
                return;
            }
            throw new NotFoundHttpException("File not found");
        }
    }

    public static function loadFileForApi(string $executionId, string $type): void
    {
        $user = User::findByUsername($executionId);
        if (($user !== null) && $type === 'archive') {
            Telegram::sendDebug("request archive file");
            $info = Table_availability::findOne(['userId' => $executionId, 'is_execution' => true]);
            if ($info !== null) {
                $path = Yii::getAlias('@executionsDirectory') . DIRECTORY_SEPARATOR . $info->file_name;
                if (is_file($path)) {
                    Yii::$app->response->sendFile($path, $info->file_name);
                    Yii::$app->response->send();
                }
            }
        }
    }

    public static function loadConclusionForApi($executionId, $offset, $realName = null): void
    {
        $user = User::findByUsername($executionId);
        if ($user !== null) {
            $info = Table_availability::findAll(['userId' => $executionId, 'is_conclusion' => true]);
            if (!empty($info)) {
                if ($realName !== null) {
                    foreach ($info as $item) {
                        if (trim($item->execution_area) === trim($realName)) {
                            $path = Yii::getAlias('@conclusionsDirectory') . DIRECTORY_SEPARATOR . $item->file_name;
                            if (is_file($path)) {
                                Yii::$app->response->sendFile($path, $item->file_name);
                                Yii::$app->response->send();
                            }
                        }
                    }
                } else {
                    $item = $info[$offset];
                    $path = Yii::getAlias('@conclusionsDirectory') . DIRECTORY_SEPARATOR . $item->file_name;
                    if (is_file($path)) {
                        Yii::$app->response->sendFile($path, $item->file_name);
                        Yii::$app->response->send();
                    }
                }
            }
        }
    }

    public static function extractZip(UploadedFile $file): array
    {
        try {
            $tempFile = Yii::$app->basePath . '/temp/' . str_replace(['-', '_'], ['', ''], Yii::$app->security->generateRandomString(50)) . '.zip';
            $file->saveAs($tempFile, false);
            $zip = new ZipArchive;
            if ($zip->open($tempFile) === TRUE) {
                $zip->extractTo(Info::EXEC_FOLDER . '\\' . str_replace(['-', '_'], ['', ''], Yii::$app->security->generateRandomString(50)));
                $zip->close();
                unlink($tempFile);
                return ['status' => 'success', 'path' => "result\\true"];
            }
            unlink($tempFile);
        } catch (\yii\base\Exception $e) {
            Telegram::sendDebug("error extracting zip: " . $e->getTraceAsString());
        }
        return ['status' => 'failed'];
    }

    public static function showScheduleHash(): void
    {
        $file = Yii::$app->getBasePath() . '/schedule/schedule.xlsx';
        if (is_file($file)) {
            echo hash_file('md5', $file);
        }
        echo 0;
    }
}