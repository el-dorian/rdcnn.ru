<?php


namespace app\models;


use app\models\database\Archive_execution;
use app\models\database\MessengerPersonalList;
use app\models\database\TempDownloadLinks;
use app\models\utils\ComHandler;
use app\models\utils\FilesHandler;
use app\models\utils\FirebaseHandler;
use app\models\utils\GrammarHandler;
use app\models\utils\MailSettings;
use app\models\utils\Management;
use app\models\utils\TimeHandler;
use app\priv\Info;
use CURLFile;
use Exception;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;
use TelegramBot\Api\InvalidArgumentException;
use TelegramBot\Api\InvalidJsonException;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Update;
use Yii;

class Telegram
{
    public static function handleRequest(): void
    {
        try {
            $token = Info::TG_BOT_TOKEN;
            /** @var BotApi|Client $bot */
            $bot = new Client($token);
// команда для start
            $bot->command(/**
             * @param $message Message
             */ 'start', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'start');
                $answer = 'Добро пожаловать! /help для вывода команд';
                /** @var Message $message */
                $bot->sendMessage($message->getChat()->getId(), $answer);
            });

// команда для помощи
            $bot->command('help', static function ($message) use ($bot) {
                try {
                    /** @var Message $message */
                    self::toLog($message->getChat()->getId(), 'help');
                    // проверю, зарегистрирован ли пользователь как работающий у нас
                    if (MessengerPersonalList::iWorkHere($message->getChat()->getId())) {
                        $answer = 'Команды:
/help - вывод справки
/cbl - очистить чёрный список IP
/fb - отправить тестовые сообщения Firebase
/test_news - отправить тестовые сообщения рассылки
/upd - обновить ПО сервера
/ping - последние активности сервера
/v - текущая версия ПО
/run_check - начать проверку обновлений файлов
/conc - список незагруженных заключений
/create_{номер обследования}_{пароль} - добавить новое обследование в ЛК
/exec - список незагруженных обследований';
                    } else {
                        $answer = 'Команды:
/help - вывод справки';
                    }
//                    $customKeyboard = [[['text' => '/a', 'callback_data' => '/a'], ['text' => '/b', 'callback_data' => '/b']]];
//                    $reply_markup = new ReplyKeyboardMarkup($customKeyboard);
                    /** @var Message $message */
                    $bot->sendMessage($message->getChat()->getId(),
                        $answer,
                        null,
                        false,
                        null,
                        null
                    );
                } catch (Exception $e) {
                    $bot->sendMessage($message->getChat()->getId(), $e->getMessage());
                }
            });
// команда для очистки чёрного списка
            $bot->command('run_check', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'check');
                Telegram::sendDebug("try to run check files");
                if (!FileUtils::isUpdateInProgress() && FileUtils::getLastUpdateTime() < (time() - 30)) {
                    $file = Yii::$app->basePath . '\\yii.bat';
                    if (is_file($file)) {
                        Telegram::sendDebug("do it");
                        $command = "$file console";
                        $outFilePath = Yii::$app->basePath . '/logs/content_change.log';
                        $outErrPath = Yii::$app->basePath . '/logs/content_change_err.log';
                        $command .= ' > ' . $outFilePath . ' 2>' . $outErrPath . ' &"';
                        try {
                            // попробую вызвать процесс асинхронно
                            /** @noinspection PhpFullyQualifiedNameUsageInspection */
                            $handle = new \COM('WScript.Shell');
                            /** @noinspection PhpUndefinedMethodInspection */
                            $handle->Run($command, 0, false);
                        } catch (Exception) {
                            exec($command);
                        }
                    }
                } else {
                    // запишу в файл отчётов, что ещё не пришло время для проверки
                    $bot->sendMessage($message->getChat()->getId(), "Таймаут обновления, потом");
                }
            });
// команда для очистки чёрного списка
            $bot->command('ping', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'ping');
                $state = "Статус проверки новых данных: "
                    . FileUtils::isUpdateInProgress() .
                    "\nПоследняя проверка: "
                    . TimeHandler::timestampToDateTime(FileUtils::getLastUpdateTime()) .
                    "\nПоследняя проверка обновлений: "
                    . TimeHandler::timestampToDateTime(FileUtils::getLastCheckUpdateTime());
                $bot->sendMessage($message->getChat()->getId(), $state);
            });
// помечу как работающего в Авроре
            $bot->command('work_aurora', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'work in aurora');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Ок, вы будете получать информацию о происходящем в Авроре");
                    MessengerPersonalList::setWork($personId, MessengerPersonalList::WORK_AURORA);
                }
            });
            $bot->command('work_nv', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'work in nv');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Ок, вы будете получать информацию о происходящем на Нижневолжской");
                    MessengerPersonalList::setWork($personId, MessengerPersonalList::WORK_NV);
                }
            });
            $bot->command('i_no_work', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'no work');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Больше не будете получать информацию, спасибо за работу");
                    MessengerPersonalList::setWork($personId, MessengerPersonalList::NO_WORK);
                }
            });
            $bot->command('add_background', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'add background');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Отправьте мне PDF файл, я добавлю туда фон и отправлю вам обратно. Если передумали, нажмите /cancel");
                    MessengerPersonalList::setLastCommand($personId, 'add_background');
                }
            });
            $bot->command('add_ct_background', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'add background');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Отправьте мне PDF файл, я добавлю туда фон и отправлю вам обратно. Если передумали, нажмите /cancel");
                    MessengerPersonalList::setLastCommand($personId, 'add_ct_background');
                }
            });
            $bot->command('patient_search', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'search patient');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Окей, будем искать данные пациента в архиве. Отправьте мне ФИО пациента или номер обследования, данные по которому вы хотите получить. Вместе с именем пациента через запятую можете указать дату его рождения в формате дд.мм.гггг, например \"Иванов Иван Иванович, 01.01.2001\". Если передумали, нажмите /cancel");
                    MessengerPersonalList::setLastCommand($personId, 'patient_search');
                }
            });
            $bot->command('cancel', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'cancel action');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, "Ожидание действия отменено");
                    MessengerPersonalList::setLastCommand($personId, null);
                }
            });
            $bot->command('show_today_aurora', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'today aurora stats');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, ExecutionHandler::getTodayStatistics(ExecutionHandler::CENTER_AURORA));
                }
            });
            $bot->command('show_today_nv', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'today nv stats');
                $personId = $message->getChat()->getId();
                if (MessengerPersonalList::iWorkHere($personId)) {
                    $bot->sendMessage($personId, ExecutionHandler::getTodayStatistics(ExecutionHandler::CENTER_NV));
                }
            });
// команда для очистки чёрного списка
            $bot->command('cbl', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'clear black list');
                /** @var Message $message */
                // проверю, зарегистрирован ли пользователь как работающий у нас
                if (MessengerPersonalList::iWorkHere($message->getChat()->getId())) {
                    Table_blacklist::clear();
                    /** @var Message $message */
                    $bot->sendMessage($message->getChat()->getId(), 'Чёрный список вычищен');
                }
            });
// команда для отображения версии сервера
            $bot->command('v', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'check software');
                self::saveLastHandledMessage(time() . " проверка версии ПО");
                /** @var Message $message */
                /** @var Message $message */
                $versionFile = Yii::$app->basePath . '\\version.info';
                if (is_file($versionFile)) {
                    try {
                        $result = $bot->sendMessage($message->getChat()->getId(), 'Текущая версия: ' . file_get_contents($versionFile));
                        if ($result !== null) {
                            FileUtils::setTelegramLog(time() . 'сообщение отправлено');
                        } else {
                            FileUtils::setTelegramLog(time() . 'сообщение не отправлено');
                        }
                    } catch (Exception $e) {
                        FileUtils::setTelegramLog('Ошибка отправки : ' . $e->getMessage());
                    }
                } else {
                    $bot->sendMessage($message->getChat()->getId(), 'Файл с версией сервера не обнаружен');
                }
            });
// команда для отправки тестовых сообщений
            $bot->command('fb', static function ($message) use ($bot) {
                /** @var Message $message */
                FirebaseHandler::sendTest();
                $bot->sendMessage($message->getChat()->getId(), 'Сообщения отправлены');
            });
            // рассылка новостей
            $bot->command('test_news', static function ($message) use ($bot) {
                /** @var Message $message */
                FirebaseHandler::sendTopicTest();
                $bot->sendMessage($message->getChat()->getId(), 'Новости отправлены');
            });
// команда для обновления ПО сервера
            $bot->command('upd', static function ($message) use ($bot) {
                self::toLog($message->getChat()->getId(), 'request update');
                /** @var Message $message */
                // проверю, зарегистрирован ли пользователь как работающий у нас
                if (MessengerPersonalList::iWorkHere($message->getChat()->getId())) {
                    Management::updateSoft();
                    /** @var Message $message */
                    $bot->sendMessage($message->getChat()->getId(), 'Обновляю ПО через телеграм-запрос');
                }
            });
// команда для вывода незагруженных заключений
            $bot->command('conc', static function ($message) use ($bot) {
                /** @var Message $message */
                // проверю, зарегистрирован ли пользователь как работающий у нас
                if (MessengerPersonalList::iWorkHere($message->getChat()->getId())) {
                    $withoutConclusions = Table_availability::getWithoutConclusions();
                    if (!empty($withoutConclusions)) {
                        $answer = "Не загружены заключения:\n " . $withoutConclusions;
                    } else {
                        $answer = 'Вау, все заключения загружены!';
                    }
                    /** @var Message $message */
                    $bot->sendMessage($message->getChat()->getId(), $answer);
                }
            });
// команда для вывода незагруженных обследований
            $bot->command('exec', static function ($message) use ($bot) {
                /** @var Message $message */
                // проверю, зарегистрирован ли пользователь как работающий у нас
                if (MessengerPersonalList::iWorkHere($message->getChat()->getId())) {
                    $withoutExecutions = Table_availability::getWithoutExecutions();
                    if (!empty($withoutExecutions)) {
                        $answer = "Не загружены файлы:\n " . $withoutExecutions;
                    } else {
                        $answer = 'Вау, все файлы загружены!';
                    }
                    /** @var Message $message */
                    $bot->sendMessage($message->getChat()->getId(), $answer);
                }
            });

            $bot->on(/**
             * @param $Update Update
             * @return string
             * @throws InvalidArgumentException
             * @throws \TelegramBot\Api\Exception
             */ static function (Update $Update) use ($bot) {
                /** @var Update $Update */
                /** @var Message $message */
                try {
                    $message = $Update->getMessage();
                    self::toLog($message->getChat()->getId(), $message->getText());
                    // save to log messages

                    $document = $message->getDocument();
                    if (MessengerPersonalList::iWorkHere($message->getChat()->getId())) {
                        if ($document !== null) {
                            $mime = $document->getMimeType();
                            if ($mime === 'application/pdf') {
                                if (MessengerPersonalList::getLastCommand($message->getChat()->getId()) === "add_background") {
                                    $bot->sendMessage($message->getChat()->getId(), 'Добавляю фон файлу');
                                    MessengerPersonalList::setLastCommand($message->getChat()->getId(), null);
                                    $file = $bot->getFile($document->getFileId());
                                    // в строке- содержимое файла
                                    $downloadedFile = $bot->downloadFile($file->getFileId());
                                    if (!empty($downloadedFile) && $downloadedFile !== '') {
                                        // сохраню полученный файл во временную папку
                                        $path = FileUtils::saveTempFile($downloadedFile, '.pdf');
                                        if (is_file($path)) {
                                            // добавлю фон
                                            $fileWithBackground = FileUtils::addBackgroundToPdfSimple($path);
                                            if (is_file($fileWithBackground)) {
                                                $file = new CURLFile($fileWithBackground, 'application/pdf');
                                                $bot->sendDocument(
                                                    $message->getChat()->getId(),
                                                    $file
                                                );
                                                unlink($fileWithBackground);
                                            } else {
                                                $bot->sendMessage($message->getChat()->getId(), 'Не удалось добавить фон, попробуйте ещё раз');
                                                unlink($path);
                                            }
//                                            $fileWithBackground
//                                            $file = new CURLFile($answer, 'application/pdf', $path);
                                        }
                                    }

                                } else if (MessengerPersonalList::getLastCommand($message->getChat()->getId()) === "add_ct_background") {
                                    $bot->sendMessage($message->getChat()->getId(), 'Добавляю фон файлу');
                                    MessengerPersonalList::setLastCommand($message->getChat()->getId(), null);
                                    $file = $bot->getFile($document->getFileId());
                                    // в строке- содержимое файла
                                    $downloadedFile = $bot->downloadFile($file->getFileId());
                                    if (!empty($downloadedFile) && $downloadedFile !== '') {
                                        // сохраню полученный файл во временную папку
                                        $path = FileUtils::saveTempFile($downloadedFile, '.pdf');
                                        if (is_file($path)) {
                                            // добавлю фон
                                            $fileWithBackground = FileUtils::addBackgroundToPdfSimple($path, true);
                                            if (is_file($fileWithBackground)) {
                                                $file = new CURLFile($fileWithBackground, 'application/pdf');
                                                $bot->sendDocument(
                                                    $message->getChat()->getId(),
                                                    $file
                                                );
                                                unlink($fileWithBackground);
                                            } else {
                                                $bot->sendMessage($message->getChat()->getId(), 'Не удалось добавить фон, попробуйте ещё раз');
                                                unlink($path);
                                            }
//                                            $fileWithBackground
//                                            $file = new CURLFile($answer, 'application/pdf', $path);
                                        }
                                    }

                                } else {
                                    $bot->sendMessage($message->getChat()->getId(), 'обрабатываю PDF');
                                    $file = $bot->getFile($document->getFileId());
                                    // в строке- содержимое файла
                                    $downloadedFile = $bot->downloadFile($file->getFileId());
                                    if (!empty($downloadedFile) && $downloadedFile !== '') {
                                        // файл получен
                                        // файл получен
                                        // сохраню полученный файл во временную папку
                                        $path = FileUtils::saveTempFile($downloadedFile, '.pdf');
                                        if (is_file($path)) {
                                            $answer = FileUtils::handleFileUpload($path);
                                            // отправлю сообщение с данными о фале
                                            $fileName = GrammarHandler::getFileName($answer);
                                            $availItem = Table_availability::findOne(['file_name' => $fileName]);
                                            if ($availItem !== null) {
                                                $bot->sendMessage($message->getChat()->getId(), "Обработано заключение\nИмя пациента: $availItem->patient_name\nОбласть обследования:$availItem->execution_area\nНомер обследования:$availItem->userId");
                                            }
                                            $file = new CURLFile($answer, 'application/pdf', $fileName);
                                            if (is_file($answer)) {
                                                $bot->sendDocument(
                                                    $message->getChat()->getId(),
                                                    $file
                                                );
                                            } else {
                                                $bot->sendMessage($message->getChat()->getId(), $answer);
                                            }
                                        }
                                    }
                                }
                            }
                            else if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                                $bot->sendMessage($message->getChat()->getId(), 'обрабатываю DOCX');
                                $file = $bot->getFile($document->getFileId());
                                // в строке- содержимое файла
                                $downloadedFile = $bot->downloadFile($file->getFileId());
                                if (!empty($downloadedFile) && $downloadedFile !== '') {
                                    // файл получен
                                    // сохраню полученный файл во временную папку
                                    $path = FileUtils::saveTempFile($downloadedFile, '.docx');
                                    self::sendFileBack($path, $bot, $message);
                                }
                            }
                            else if ($mime === 'application/msword') {
                                $bot->sendMessage($message->getChat()->getId(), 'обрабатываю DOC');
                                $file = $bot->getFile($document->getFileId());
                                // в строке- содержимое файла
                                $downloadedFile = $bot->downloadFile($file->getFileId());
                                if (!empty($downloadedFile) && $downloadedFile !== '') {
                                    // файл получен
                                    // сохраню полученный файл во временную папку
                                    $path = FileUtils::saveTempFile($downloadedFile, '.doc');
                                    self::sendFileBack($path, $bot, $message);
                                }
                            }
                            else if ($mime === 'application/zip') {
                                $bot->sendMessage($message->getChat()->getId(), 'Разбираю архив');
                                $dlFile = $bot->getFile($document->getFileId());
                                // скачаю файл в фоновом режиме
                                $file = Yii::$app->basePath . '\\yii.bat';
                                if (is_file($file)) {
                                    $command = "$file console/handle-zip " . $dlFile->getFileId() . ' ' . $message->getChat()->getId();
                                    ComHandler::runCommand($command);
                                }
                            }
                            else if ($mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'){
                                $bot->sendMessage($message->getChat()->getId(), 'prepare schedule');
                                $dlFile = $bot->getFile($document->getFileId());
                                $downloadedFile = $bot->downloadFile($dlFile->getFileId());
                                FileUtils::saveScheduleFile($downloadedFile);
                                $bot->sendMessage($message->getChat()->getId(), 'saved');
                                FirebaseHandler::sendNewScheduleAvailableMessage();
//                                if (!empty($downloadedFile) && $downloadedFile !== '') {
//                                    $path = FileUtils::saveTempFile($downloadedFile, '.xlsx');
//                                    if (is_file($path)) {
//                                        // save to schedule dir
//                                        FileUtils::saveScheduleFile($path);
//                                        $bot->sendMessage($message->getChat()->getId(), 'schedule downloaded');
//                                    }
//                                }
                            }
                            else {
                                $bot->sendMessage($message->getChat()->getId(), 'Я понимаю только файлы в формате PDF и DOCX (и ZIP)');
                            }
                        } else {
                            // зарегистрируюсь для получения ошибок обработки
                            $msg_text = $message->getText();
                            if ($msg_text === 'register for errors') {
                                MessengerPersonalList::subscribeGetErrors($message->getChat()->getId());
                                $bot->sendMessage($message->getChat()->getId(), 'Вы подписаны на получение ошибок');
                                return '';
                            }
                            if (GrammarHandler::startsWith($msg_text, "/create_")) {
                                // register new user, if not exists with current parameters
                                $parameters = mb_split('_', $msg_text);
                                if (count($parameters) === 3) {
                                    $username = trim($parameters[1]);
                                    $pass = trim($parameters[2]);
                                    if (!empty($username) && !empty($pass)) {
                                        if (User::registerIfNot($username, $pass)) {
                                            $bot->sendMessage($message->getChat()->getId(), "Зарегистрировано обследование с логином $username и паролем $pass");
                                        } else {
                                            $bot->sendMessage($message->getChat()->getId(), "Регистрация не удалась. Или обследование уже существует или что-то не то с номером обследования и паролем");
                                        }
                                        return '';
                                    }
                                }
                            }
                            if (GrammarHandler::startsWith($msg_text, "/dl_")) {
                                // find all files and create a temp links for it
                                $executionId = substr($msg_text, 4);
                                // find files
                                $user = User::findByUsername($executionId);
                                if ($user !== null) {
                                    $existentFiles = Table_availability::getFilesInfo($user);
                                    if (!empty($existentFiles)) {
                                        $answer = '';
                                        foreach ($existentFiles as $file) {
                                            $answer .= $file['name'] . "\n";
                                            $link = TempDownloadLinks::createLink($user, $file['type'], $file['fileName']);
                                            $answer .= "https://rdcnn.ru/dl/$link->link\n";
                                            //$answer .= 'Ссылка действительна только для одной загрузки!';
                                        }
                                        $bot->sendMessage($message->getChat()->getId(), $answer);

                                    } else {
                                        $bot->sendMessage($message->getChat()->getId(), 'Файлов по данному обследованию не найдено');
                                    }
                                } else {
                                    $bot->sendMessage($message->getChat()->getId(), 'Файлов по данному обследованию не найдено');
                                }
                                return $executionId;
                            }
                            if (GrammarHandler::startsWith($msg_text, "/today_list_")) {
                                $bot->sendMessage($message->getChat()->getId(), ExecutionHandler::getTodayExecutionList(substr($msg_text, 12)));
                                return '';
                            }
                            if (GrammarHandler::startsWith($msg_text, "/today_no_conclusion_")) {
                                $bot->sendMessage($message->getChat()->getId(), ExecutionHandler::getTodayNoConclusion(substr($msg_text, 21)));
                                return '';
                            }
                            if (GrammarHandler::startsWith($msg_text, "/adl_")) {
                                $fileId = substr($msg_text, 5);
                                if (!empty($fileId)) {
                                    $clearFileId = (int)$fileId;
                                    if ($clearFileId > 0) {
                                        $execution = Archive_execution::findOne(['id' => $clearFileId]);
                                        if ($execution !== null) {
                                            $path = Info::PDF_ARCHIVE_PATH . $execution->path;
                                            if (is_file($path)) {
                                                $bot->sendMessage($message->getChat()->getId(), 'Файл найден, отправляю');
                                                $file = new CURLFile($path, 'application/pdf');
                                                $bot->sendDocument(
                                                    $message->getChat()->getId(),
                                                    $file
                                                );
                                                return '';
                                            }
                                        }
                                    }
                                }
                                $bot->sendMessage($message->getChat()->getId(), "Заключение не найдено в архиве. Сообщите Сергею");
                                return '';
                            }
                            if (GrammarHandler::startsWith($msg_text, "/today_no_dicom_")) {
                                $bot->sendMessage($message->getChat()->getId(), ExecutionHandler::getTodayNoExecution(substr($msg_text, 16)));
                                return '';
                            }
                            if (GrammarHandler::startsWith($msg_text, "/qr_")) {
                                $file = LoginQr::generateQrFile(substr($msg_text, 4));
                                if ($file !== null) {
                                    $curlFile = new CURLFile($file, 'image/png', 'qr.png');
                                    $bot->sendDocument(
                                        $message->getChat()->getId(),
                                        $curlFile
                                    );
                                    unlink($file);
                                }
                                return '';
                            }
                            if (MessengerPersonalList::getLastCommand($message->getChat()->getId()) === 'patient_search') {
                                $bot->sendMessage($message->getChat()->getId(), "Поиск по запросу $msg_text");
                                $bot->sendMessage($message->getChat()->getId(), Archive_execution::findInArchive($msg_text));
                                MessengerPersonalList::setLastCommand($message->getChat()->getId(), null);
                                return '';
                            }
                            $bot->sendMessage($message->getChat()->getId(), $msg_text);
                        }
                    } else {
                        $msg_text = $message->getText();
                        // получен простой текст, обработаю его в зависимости от содержимого
                        $answer = self::handleSimpleText($msg_text, $message);
                        $bot->sendMessage($message->getChat()->getId(), $answer);
                    }
                } catch (Exception $e) {
                    $bot->sendMessage($message->getChat()->getId(), $e->getMessage());
                }
                return '';
            }, static function () {
                return true;
            });

            try {
                $bot->run();
            } catch (InvalidJsonException) {
                // что-то сделаю потом
            }
        } catch (Exception $e) {
            // запишу ошибку в лог
            $file = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/logs/telebot_err_' . time() . '.log';
            $report = $e->getMessage();
            file_put_contents($file, $report);
        }
    }

    private static function handleSimpleText(string $msg_text, Message $message): string
    {
        switch ($msg_text) {
            // если введён токен доступа- уведомлю пользователя об успешном входе в систему
            case Info::VIBER_SECRET:
                // регистрирую получателя
                MessengerPersonalList::register($message->getChat()->getId());
                return 'Ага, вы работаете на нас :) /help для списка команд';
            default:
                return 'Не понимаю, о чём вы :( (вы написали ' . $msg_text . ')';
        }
    }

    /**
     * @param string $path
     * @param $bot
     * @param Message $message
     * @throws \TelegramBot\Api\Exception
     * @throws InvalidArgumentException
     * @throws \yii\base\Exception
     */
    private static function sendFileBack(string $path, $bot, Message $message): void
    {
        /** @var BotApi|Client $bot */
        if (is_file($path)) {
            $answer = FileUtils::handleFileUpload($path);
            $file = new CURLFile($answer, 'application/pdf', GrammarHandler::getFileName($answer));
            if (is_file($answer)) {
                $bot->sendDocument(
                    $message->getChat()->getId(),
                    $file
                );
            } else {
                $bot->sendMessage($message->getChat()->getId(), $answer);
            }
            unlink($path);
        }
    }

    /**
     * @param string $errorInfo
     */
    public static function sendDebug(string $errorInfo): void
    {
        $errorInfo = urlencode($errorInfo);
        $file = Yii::$app->basePath . '\\yii.bat';
        if (is_file($file)) {
            $command = "$file console/telegram-send-debug \"$errorInfo\"";
            ComHandler::runCommand($command);
        }
//        $errorCounter = 0;
//        while (true) {
//            try {
//                // проверю, есть ли учётные записи для отправки данных
//                $subscribers = MessengerPersonalList::findAll(['get_errors' => 1]);
//                if (!empty($subscribers)) {
//                    $token = Info::TG_BOT_TOKEN;
//                    /** @var BotApi|Client $bot */
//                    $bot = new Client($token);
//                    foreach ($subscribers as $subscriber) {
//                        $bot->sendMessage($subscriber->messenger_id, $errorInfo);
//                    }
//                }
//                break;
//            } catch (Exception $e) {
//                $errorCounter++;
//                if ($errorCounter > 5) {
//                    try {
//                        // отправлю письмо с ошибкой на почту
//                        $mail = Yii::$app->mailer->compose()
//                            ->setFrom([MailSettings::getInstance()->address => 'РДЦ'])
//                            ->setSubject('Не получилось отправить сообщение боту')
//                            ->setHtmlBody($errorInfo . "<br/>" . $e->getMessage())
//                            ->setTo(['eldorianwin@gmail.com' => 'eldorianwin@gmail.com', 'osv0d@rdcnn.ru' => 'Дмитрий']);
//                        // попробую отправить письмо, в случае ошибки- вызову исключение
//                        $mail->send();
//                    } catch (Exception $e) {
//                        self::sendDebug($e->getTraceAsString());
//                    }
//                    break;
//                }
//            }
//        }
    }

    public static function downloadZip(string $fileId, $clientId): void
    {
        try {
            $token = Info::TG_BOT_TOKEN;
            /** @var BotApi|Client $bot */
            $bot = new Client($token);
            $file = $bot->getFile($fileId);
            $downloadedFile = $bot->downloadFile($file->getFileId());
            $bot->sendMessage($clientId, 'Архив скачан');
            $path = FileUtils::saveTempFile($downloadedFile, '.zip');
            if (is_file($path)) {
                // сохраню файл
                $num = FilesHandler::unzip($path);
                if ($num !== null) {
                    $bot->sendMessage($clientId, 'Добавлены файлы сканирования обследования ' . $num);
                } else {
                    $bot->sendMessage($clientId, 'Не смог обработать архив');
                }
            }
        } catch (Exception $e) {
            self::sendDebug("Ошибка при обработке команды: " . $e->getMessage());
        }
    }

    /**
     * @param string $message
     */
    private static function saveLastHandledMessage(string $message): void
    {
        $file = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/logs/last_tg_message.log';
        file_put_contents($file, $message);
    }

    public static function notifyConclusionAdded(Table_availability $item): void
    {
        $isAurora = str_starts_with($item->userId, "A");
        $subscribers = $isAurora ? MessengerPersonalList::getWorkers(MessengerPersonalList::WORK_AURORA) : MessengerPersonalList::getWorkers(MessengerPersonalList::WORK_NV);
        if ($subscribers !== null) {
            $message = "Добавлено заключение: \n$item->userId\n$item->patient_name\n$item->execution_area\nСписок обследований:\n/today_list_" . ($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV) . "\n";
            $withoutConclusions = ExecutionHandler::countWithoutConclusionsToday($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV);
            $withoutExecutions = ExecutionHandler::countWithoutExecutionsToday($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV);
            if ($withoutConclusions > 0) {
                $message .= "Без заключений: $withoutConclusions. Подробный список: /today_no_conclusion_" . ($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV) . "\n";
            }
            if ($withoutExecutions > 0) {
                $message .= "Без снимков: $withoutExecutions. Подробный список: /today_no_dicom_" . ($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV) . "\n";
            }
            $token = Info::TG_BOT_TOKEN;
            /** @var BotApi|Client $bot */
            $bot = new Client($token);
            foreach ($subscribers as $subscriber) {
                $errorCounter = 0;
                while (true) {
                    try {
                        $bot->sendMessage($subscriber->messenger_id, $message);
                        break;
                    } catch (InvalidArgumentException | \TelegramBot\Api\Exception) {
                        $errorCounter++;
                        if ($errorCounter > 5) {
                            break;
                        }
                    }
                }
            }
        }
    }

    public static function sendExecutionAdded(Table_availability $item): void
    {
        $isAurora = str_starts_with($item->userId, "A");
        $subscribers = $isAurora ? MessengerPersonalList::getWorkers(MessengerPersonalList::WORK_AURORA) : MessengerPersonalList::getWorkers(MessengerPersonalList::WORK_NV);
        if ($subscribers !== null) {
            $message = "Добавлены снимки по обследованию: \n$item->userId\nСписок обследований:\n/today_list_" . ($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV) . "\n";
            $withoutConclusions = ExecutionHandler::countWithoutConclusionsToday($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV);
            $withoutExecutions = ExecutionHandler::countWithoutExecutionsToday($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV);
            if ($withoutConclusions > 0) {
                $message .= "Без заключений: $withoutConclusions. Подробный список: /today_no_conclusion_" . ($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV) . "\n";
            }
            if ($withoutExecutions > 0) {
                $message .= "Без снимков: $withoutExecutions. Подробный список: /today_no_dicom_" . ($isAurora ? MessengerPersonalList::WORK_AURORA : MessengerPersonalList::WORK_NV) . "\n";
            }
            $token = Info::TG_BOT_TOKEN;
            /** @var BotApi|Client $bot */
            $bot = new Client($token);
            foreach ($subscribers as $subscriber) {
                $errorCounter = 0;
                while (true) {
                    try {
                        $bot->sendMessage($subscriber->messenger_id, $message);
                        break;
                    } catch (InvalidArgumentException | \TelegramBot\Api\Exception) {
                        $errorCounter++;
                        if ($errorCounter > 5) {
                            break;
                        }
                    }
                }
            }
        }
    }

    private static function toLog($personId, $report): void
    {
        $file = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/logs/telebot_history_' . $personId . '.log';
        file_put_contents($file, time() . " $report" . "\n", FILE_APPEND);
    }

    public static function sendDebugFile(string $path): void
    {
        if (is_file($path)) {
            $file = new CURLFile($path, 'application/pdf', "Файл с ошибкой.pdf");
            $errorCounter = 0;
            while (true) {
                try {
                    // проверю, есть ли учётные записи для отправки данных
                    $subscribers = MessengerPersonalList::findAll(['get_errors' => 1]);
                    if (!empty($subscribers)) {
                        $token = Info::TG_BOT_TOKEN;
                        /** @var BotApi|Client $bot */
                        $bot = new Client($token);
                        foreach ($subscribers as $subscriber) {
                            $bot->sendDocument(
                                $subscriber->messenger_id,
                                $file
                            );
                        }
                    }
                    break;
                } catch (Exception) {
                    $errorCounter++;
                    if ($errorCounter > 5) {
                        break;
                    }
                }
            }
        }
    }
}