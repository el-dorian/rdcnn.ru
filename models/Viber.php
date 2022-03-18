<?php


namespace app\models;


use app\models\database\TempDownloadLinks;
use app\models\database\ViberMessaging;
use app\models\database\MessengerPersonalList;
use app\models\database\ViberSubscriptions;
use app\models\utils\GrammarHandler;
use app\priv\Info;
use Exception;
use phpDocumentor\Reflection\Types\Self_;
use TelegramBot\Api\InvalidArgumentException;
use Viber\Api\Event;
use Viber\Api\Keyboard;
use Viber\Api\Keyboard\Button;
use Viber\Api\Message\File;
use Viber\Api\Message\Text;
use Viber\Api\Sender;
use Viber\Bot;
use Viber\Client;
use Yii;
use yii\base\Model;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

class Viber extends Model
{

    public const CONCLUSIONS = 'заключения';
    public const EXECUTIONS = 'файлы';
    public const DOWNLOADS_COUNT = 'статистика загрузок';
    public const VIBER_FILE_SIZE_LIMIT = 52428800;

    /**
     * @param $apiKey
     * @return Bot
     */
    public static function getBot($apiKey): Bot
    {
        return new Bot(['token' => $apiKey]);
    }

    /**
     * @return Sender
     */
    public static function getBotSender(): Sender
    {
        return new Sender([
            'name' => 'Бот РДЦ',
            'avatar' => 'https://rdcnn.ru/images/bot.png',
        ]);
    }

    /**
     * @param string $userName
     * @throws \yii\base\Exception
     */
    public static function notifyExecutionLoaded($userName): void
    {
        // создам временную ссылку на скачивание и отправлю её подписанным на данное обследование
        $execution = User::findByUsername($userName);
        if ($execution !== null) {
            // найду всех, кто подписан на данное обследование
            $subscribers = ViberSubscriptions::findAll(['patient_id' => $execution->id]);
            if (!empty($subscribers)) {
                foreach ($subscribers as $subscriber) {
                    $link = TempDownloadLinks::createLink($execution, 'execution');
                    if ($link !== null) {
                        self::sendTempLink($subscriber->viber_id, $link->link);
                    }
                }
            }
        }
    }

    /**
     * @param $fileName
     * @throws \yii\base\Exception
     */
    public static function notifyConclusionLoaded($fileName): void
    {
        // создам временную ссылку на скачивание и отправлю её подписанным на данное обследование
        $execution = User::findByUsername(GrammarHandler::getBaseFileName($fileName));
        if ($execution !== null) {
            // найду всех, кто подписан на данное обследование
            $subscribers = ViberSubscriptions::findAll(['patient_id' => $execution->id]);
            if (!empty($subscribers)) {
                foreach ($subscribers as $subscriber) {
                    $link = TempDownloadLinks::createLink($execution, 'conclusion', $fileName);
                    if ($link !== null) {
                        self::sendTempLink($subscriber->viber_id, $link->link);
                    }
                }
            }
        }
    }

    /**
     * регистрация хука
     */
    public static function setup(): void
    {

        $apiKey = Info::VIBER_API_KEY;
        $webHookUrl = 'https://rdcnn.ru/viber/connect';
        try {
            $client = new Client(['token' => $apiKey]);
            $client->setWebhook($webHookUrl);
            echo "Success!\n";
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public static function handleRequest(): void
    {
        // проверю, если сообщение уже обработано- ничего не делаю
        if (self::handledYet()) {
            return;
        }
        $apiKey = Info::VIBER_API_KEY;
        // придётся добавить свою обработку- проверяю загрузку файлов
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            // разберу запрос
            $json = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            $senderId = $json['sender']['id'];
            if (!empty($json) && !empty($json['message']) && !empty($json['message']['type']) && $json['message']['type'] === 'file' && MessengerPersonalList::iWorkHere($senderId)) {
                // оповещу о получении файла
                // проверю, что заявленный файл является PDF
                if (GrammarHandler::isPdf($json['message']['file_name'])) {
                    // получу базовое название файла
                    $basename = GrammarHandler::getBaseFileName($json['message']['file_name']);
                    if (!empty($basename)) {
                        $name = GrammarHandler::toLatin($basename);
                        // проверю, зарегистрировано ли уже обследование с данным номером,
                        // так как будем подгружать заключения только к зарегистрированным
                        $execution = User::findByUsername($name);
                        if ($execution !== null) {
                            $realName = GrammarHandler::toLatin($json['message']['file_name']);
                            // ещё раз удостоверюсь, что файл подходит для загрузки
                            $strictPattern = '/^A?\d+-?\d*\.pdf$/';
                            if (preg_match($strictPattern, $realName)) {
                                // загружу файл
                                $file = file_get_contents($json['message']['media']);
                                if (!empty($file)) {
                                    file_put_contents(Yii::getAlias('@conclusionsDirectory') . '\\' . $realName, $file);
                                    self::sendMessage(
                                        self::getBot($apiKey),
                                        self::getBotSender(),
                                        $senderId,
                                        'Заключение ' . $realName . ' успешно добавлено'
                                    );
                                } else {
                                    // не удалось загрузить файл, сообщу об ошибке
                                    self::sendMessage(
                                        self::getBot($apiKey),
                                        self::getBotSender(),
                                        $senderId,
                                        'Заключение ' . $realName . ' не удалось загрузить. Попробуйте ещё раз'
                                    );
                                }
                            } else {
                                self::sendMessage(
                                    self::getBot($apiKey),
                                    self::getBotSender(),
                                    $senderId,
                                    'Заключение ' . $realName . ' : неверное имя файла. Назовите файл в соответствие с правилами'
                                );
                            }
                        } else {
                            self::sendMessage(
                                self::getBot($apiKey),
                                self::getBotSender(),
                                $senderId,
                                'Не удалось найти обследование с данным номером. Сначала администраторы должны его зарегистрировать'
                            );
                        }
                    } else {
                        self::sendMessage(
                            self::getBot($apiKey),
                            self::getBotSender(),
                            $senderId,
                            'Проверьте правильность названия файла, я не смог его разобрать'
                        );
                    }
                } else {
                    self::sendMessage(
                        self::getBot($apiKey),
                        self::getBotSender(),
                        $senderId,
                        'Вы можете загружать только файлы PDF!'
                    );
                }
            }
        }


// так будет выглядеть наш бот (имя и аватар - можно менять)
        $botSender = new Sender([
            'name' => 'Бот РДЦ',
            'avatar' => 'https://rdcnn.ru/images/bot.png',
        ]);

        try {
            $bot = new Bot(['token' => $apiKey]);
            $bot
                // При подключении бота
                ->onConversation(static function () use ($bot, $botSender) {
                    return (new Text())
                        ->setSender($botSender)
                        ->setText('Добрый день. Я бот РДЦ. Выберите, что вы хотите сделать')
                        ->setKeyboard(
                            (new Keyboard())
                                ->setButtons([
                                    (new Button())
                                        ->setBgColor('#2fa4e7')
                                        ->setTextHAlign('center')
                                        ->setActionType('reply')
                                        ->setActionBody('get-data')
                                        ->setText('Получить заключение'),
                                    (new Button())
                                        ->setBgColor('#2fa4e7')
                                        ->setTextHAlign('center')
                                        ->setActionType('reply')
                                        ->setActionBody('more-actions')
                                        ->setText('Что-то ещё'),

                                ])
                        );
                })
                // действие при подписке
                ->onSubscribe(static function () use ($bot, $botSender) {
                    $bot->getClient()->sendMessage(
                        (new Text())
                            ->setSender($botSender)
                            ->setText('Сейчас продолжим')
                    );
                })
                ->onText(/**
                 * @param $event Event
                 */ '|get-data|s', static function ($event) use ($bot, $botSender) {
                    $receiverId = $event->getSender()->getId();
                    $bot->getClient()->sendMessage(
                        (new Text())
                            ->setSender($botSender)
                            ->setReceiver($receiverId)
                            ->setText('Введите через пробел номер обследования и пароль, например: 1 432')
                    );
                })
                ->onText('|more-actions|s', static function ($event) use ($bot, $botSender) {
                    $receiverId = $event->getSender()->getId();
                    $bot->getClient()->sendMessage(
                        (new Text())
                            ->setSender($botSender)
                            ->setReceiver($receiverId)
                            ->setText('Напишите, что бы вы хотели сделать')
                    );
                })
                ->onText('|.+|s', static function ($event) use ($bot, $botSender) {
                    $receiverId = $event->getSender()->getId();
                    $text = $event->getMessage()->getText();
                    self::handleTextRequest($receiverId, $text, $bot, $botSender);
                })
                ->run();
        } catch (Exception $e) {
            $file = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/logs/viber_err_' . time() . '.log';
            $report = $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine();
            file_put_contents($file, $report);
        }
    }

    public static function subscribe($receiverId, $patientId): void
    {
        // проверю, не подписан ли уже пользователь
        $existentSubscribe = ViberSubscriptions::findOne(['viber_id' => $receiverId]);
        if ($existentSubscribe !== null) {
            $existentSubscribe->patient_id = $patientId;
            $existentSubscribe->save();
        } else {
            (new ViberSubscriptions(['patient_id' => $patientId, 'viber_id' => $receiverId]))->save();
            self::logAction('подписка оформлена');
        }
    }


    /**
     * @param $receiverId
     * @param $text
     * @param $bot
     * @param $botSender
     * @throws \yii\base\Exception
     */
    public static function handleTextRequest($receiverId, $text, $bot, $botSender): void
    {
        $lowerText = mb_strtolower($text);
        $workHere = MessengerPersonalList::iWorkHere($receiverId);
        self::logMessaging($receiverId, $text, self::getMessageToken());
        $executionPattern = '/^([aа]?\d+) (\d{4})$/ui';
        if (preg_match($executionPattern, $text, $matches)) {
            $executionNumber = $matches[1];
            self::sendMessage($bot, $botSender, $receiverId, 'Ищу информацию об обследовании № ' . $executionNumber);
            $execution = User::findByUsername($executionNumber);
            if ($execution === null) {
                self::sendMessage($bot, $botSender, $receiverId, "Обследование №$executionNumber не найдено! Попробуйте ввести данные ещё раз.");
            } else {
                // проверю, подходит ли пароль
                if ($execution->failed_try > 20) {
                    self::sendMessage($bot, $botSender, $receiverId, 'Было выполнено слишком много неверных попыток ввода пароля. В целях безопасности данные были удалены. Вы можете обратиться к нам для восстановления доступа');
                    return;
                }

                $password = $matches[2];
// проверю совпадение пароля, если не совпадает- зарегистрирую ошибку
                if (!$execution->validatePassword($password)) {
                    $execution->last_login_try = time();
                    $execution->failed_try = ++$execution->failed_try;
                    $execution->save();
                    self::sendMessage($bot, $botSender, $receiverId, 'Вы ввели неверный номер обследования или неправильный пароль. Можете попробовать ещё раз или обратиться к нам за помощью по номеру 2020200');
                } else {
                    self::sendMessage($bot, $botSender, $receiverId, 'Вы ввели верные данные, спасибо. Вы получите результаты как только они будут готовы!');
                    self::subscribe($receiverId, $execution->id);
                    ExecutionHandler::checkAvailabilityForBots($execution->id, $receiverId);
                }
            }
        } elseif ($lowerText === 'привет') {
            self::sendMessageWithButtons(
                $bot,
                $botSender,
                $receiverId,
                'Привет! Введите команду, если знаете, чего хотите, или нажмите на кнопку "команды", чтобы узнать, что я умею делать ;)',
                self::getHelpButtons()
            );

        } elseif ($lowerText === 'я работаю в рдц') {
            // запрос доступа к приватным данным
            self::sendMessage($bot, $botSender, $receiverId, 'Докажите');
        } elseif ($text === Info::VIBER_SECRET) {
            // регистрирую пользователя как нашего сотрудника
            MessengerPersonalList::register($receiverId);
            self::sendMessage($bot, $botSender, $receiverId, 'Ок, вы работаете у нас. Теперь у вас есть доступ к закрытым функциям. Чтобы увидеть список команд, введите "команды"');
        } elseif ($lowerText === 'команды') {
            if ($workHere) {
                // отправлю список команд, которые может выполнять сотрудник
                self::sendMessageWithButtons(
                    $bot,
                    $botSender,
                    $receiverId,
                    "'" . self::CONCLUSIONS . "' : выводит список пациентов без заключений\n'файлы' : выводит список пациентов без загруженных файлов обследования\n'статистика загрузок' : выводит статистику загрузок заключений и файлов\nсписок будет дополняться по мере развития",
                    self::getAdminActionButtons());
            } else {
                self::sendMessage(
                    $bot,
                    $botSender,
                    $receiverId,
                    'Введите через пробел ваш номер обследования и пароль, чтобы получить результаты'
                );
            }
        } elseif ($lowerText === self::CONCLUSIONS && $workHere) {
            // получу список обследований без заключений
            $withoutConclusions = Table_availability::getWithoutConclusions();
            if (!empty($withoutConclusions)) {
                $list = "Не загружены заключения:\n " . $withoutConclusions;
                self::sendMessageWithButtons(
                    $bot,
                    $botSender,
                    $receiverId,
                    $list,
                    self::getAdminActionButtons()
                );
            } else {
                self::sendMessageWithButtons(
                    $bot,
                    $botSender,
                    $receiverId,
                    'Вау, все заключения загружены!',
                    self::getAdminActionButtons()
                );
            }
        } elseif ($lowerText === self::EXECUTIONS && $workHere) {
            $withoutExecutions = Table_availability::getWithoutExecutions();
            if (!empty($withoutExecutions)) {
                $list = "Не загружены файлы:\n " . $withoutExecutions;
                self::sendMessageWithButtons(
                    $bot,
                    $botSender,
                    $receiverId,
                    $list,
                    self::getAdminActionButtons()
                );
            } else {
                self::sendMessageWithButtons(
                    $bot,
                    $botSender,
                    $receiverId,
                    'Вау, все файлы загружены!',
                    self::getAdminActionButtons()
                );
            }
        } elseif ($lowerText === self::DOWNLOADS_COUNT) {
            // получу данные по загрузкам
            self::sendMessageWithButtons(
                $bot,
                $botSender,
                $receiverId,
                Table_statistics::getFullState(),
                self::getAdminActionButtons()
            );
        } else {
            self::sendMessage($bot,
                $botSender,
                $receiverId,
                'Не понял, что вы имеете в виду :( Чтобы узнать, что я умею- напишите мне "команды"'
            );
        }
    }

    /**
     * @param $receiverId
     * @param $text <p>Текст сообщения</p>
     * @param $messageToken <p>Токен сообщения</p>
     */
    public static function logMessaging($receiverId, $text, $messageToken): void
    {
        (new ViberMessaging(['timestamp' => time(), 'text' => $text, 'receiver_id' => $receiverId, 'message_token' => $messageToken]))->save();
    }

    public static function logAction($text): void
    {
        $file = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/logs/viber_log_' . time() . '.log';
        file_put_contents($file, $text);
    }

    /**
     * Отправляю ссобщение пользователю
     * @param $bot Bot
     * @param $botSender
     * @param $receiverId
     * @param $text
     */
    private static function sendMessage($bot, $botSender, $receiverId, $text): void
    {
        $bot->getClient()->sendMessage(
            (new Text())
                ->setSender($botSender)
                ->setReceiver($receiverId)
                ->setText($text)
        );
    }

    /**
     * @param string $subscriberId
     * @param string $link
     */
    public static function sendTempLink(string $subscriberId, string $link): void
    {
        $bot = new Bot(['token' => Info::VIBER_API_KEY]);
        $botSender = new Sender([
            'name' => 'Бот РДЦ',
            'avatar' => 'https://rdcnn.ru/images/bot.png',
        ]);
        $linkInfo = TempDownloadLinks::findOne(['link' => $link]);
        if ($linkInfo !== null) {
            if ($linkInfo->file_type === 'conclusion') {
                self::sendMessage($bot, $botSender, $subscriberId, 'Заключение врача готово!');
            } else {
                self::sendMessage($bot, $botSender, $subscriberId, 'Файлы сканирования загружены!');
            }
            self::sendFile($subscriberId, $link);
        }
    }

    /**
     * @param string $subscriberId
     * @param string $link
     */
    private static function sendFile(string $subscriberId, string $link): void
    {
        $linkInfo = TempDownloadLinks::findOne(['link' => $link]);
        if ($linkInfo !== null) {
            if ($linkInfo->file_type === 'execution') {
                $file = Yii::getAlias('@executionsDirectory') . '\\' . $linkInfo->file_name;
                $typeText = 'Загрузите файлы сканирования по ссылке: ';
            } else {
                $file = Yii::getAlias('@conclusionsDirectory') . '\\' . $linkInfo->file_name;
                $typeText = 'Загрузите заключение врача по ссылке: ';
            }
            if (is_file($file)) {
                self::logAction(Url::toRoute(['download/download-temp', 'link' => $link], 'https'));
                // проверю, что размер файла не превышает максимально допустимый
                $fileSize = filesize($file);
                $bot = self::getBot(Info::VIBER_API_KEY);
                $botSender = self::getBotSender();
                if ($fileSize < self::VIBER_FILE_SIZE_LIMIT) {
                    $bot->getClient()->sendMessage(
                        (new File())
                            ->setSender($botSender)
                            ->setReceiver($subscriberId)
                            ->setSize(filesize($file))
                            ->setFileName($linkInfo->file_name)
                            ->setMedia(Url::toRoute(['download/download-temp', 'link' => $link], 'https'))
                    );
                } else {
                    // отправлю ссылку на скачивание файла
                    self::sendMessage(
                        $bot,
                        $botSender,
                        $subscriberId,
                        $typeText . Url::toRoute(['download/download-temp', 'link' => $link], 'https')
                    );
                }
            }
        }
    }

    /**
     * @param $link
     * @throws NotFoundHttpException
     */
    public static function downloadTempFile($link): void
    {
        // получу данные
        $linkInfo = TempDownloadLinks::findOne(['link' => $link]);
        if ($linkInfo !== null) {
            $executionInfo = User::findIdentity($linkInfo->execution_id);
            if ($executionInfo !== null) {
                // получу путь к файлу
                if ($linkInfo->file_type === 'execution') {
                    $file = Yii::getAlias('@executionsDirectory') . '\\' . $linkInfo->file_name;
                    $fileName = $executionInfo->username . '.zip';
                    Table_statistics::plusExecutionDownload($executionInfo->username);
                } else if ($linkInfo->file_type === 'conclusion') {
                    $availData = Table_availability::findOne(['file_name' => $linkInfo->file_name]);
                    if ($availData !== null) {
                        $file = Yii::getAlias('@conclusionsDirectory') . '\\' . $linkInfo->file_name;
                        $fileName = 'МРТ ' . $availData->execution_area . ' ' . $availData->patient_name . '.pdf';

                        Table_statistics::plusConclusionDownload($executionInfo->username);
                    }
                }
                else if ($linkInfo->file_type === 'archive'){
                    $file = Info::PDF_ARCHIVE_PATH . $linkInfo->file_name;
                    $fileName = 'from_archive.pdf';
                }
            }
            if (!empty($file) && !empty($fileName) && is_file($file)) {
                // отдам файл на выгрузку
                Yii::$app->response->sendFile($file, $fileName);
                return;
            }
        }
        // страница не найдена, видимо, ссылка истекла
        throw new NotFoundHttpException('Не удалось найти файлы по данной ссылке, видимо, они удалены по истечению срока давности. Вы можете обратиться к нам за повторной публикацией файлов');
    }

    public static function getMessageToken()
    {
        $input = file_get_contents('php://input');
        $json = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        return $json['message_token'];
    }

    public static function handledYet()
    {
        return ViberMessaging::find()->where(['message_token' => self::getMessageToken()])->count();
    }

    /**
     * @param $bot Bot
     * @param $botSender
     * @param $receiverId
     * @param string $text
     * @param array $buttons
     */
    private static function sendMessageWithButtons($bot, $botSender, $receiverId, string $text, array $buttons): void
    {
        $bot->getClient()->sendMessage(
            (new Text())
                ->setSender($botSender)
                ->setReceiver($receiverId)
                ->setText($text)
                ->setKeyboard(
                    (new Keyboard())
                        ->setButtons($buttons)
                )
        );
    }

    /**
     * @return array
     */
    private static function getAdminActionButtons(): array
    {
        return [(new Button())
            ->setBgColor('#2fa4e7')
            ->setTextHAlign('center')
            ->setActionType('reply')
            ->setActionBody(self::CONCLUSIONS)
            ->setText('Обследования без заключений'),
            (new Button())
                ->setBgColor('#2fa4e7')
                ->setTextHAlign('center')
                ->setActionType('reply')
                ->setActionBody('файлы')
                ->setText('Обследования без изображений'),
            (new Button())
                ->setBgColor('#2fa4e7')
                ->setTextHAlign('center')
                ->setActionType('reply')
                ->setActionBody('статистика загрузок')
                ->setText('Статистика загрузок'),
        ];
    }

    /**
     * @return array
     */
    private static function getHelpButtons(): array
    {
        return [(new Button())
            ->setBgColor('#2fa4e7')
            ->setTextHAlign('center')
            ->setActionType('reply')
            ->setActionBody('команды')
            ->setText('Показать доступные команды'),
        ];
    }

    public static function downloadOnceFile($link)
    {
        // получу данные
        $linkInfo = TempDownloadLinks::findOne(['link' => $link]);
        if ($linkInfo !== null) {
            $executionInfo = User::findIdentity($linkInfo->execution_id);
            if ($executionInfo !== null) {
                // получу путь к файлу
                if ($linkInfo->file_type === 'execution') {
                    $file = Yii::getAlias('@executionsDirectory') . '\\' . $linkInfo->file_name;
                    $fileName = $executionInfo->username . '.zip';
                } else if ($linkInfo->file_type === 'conclusion') {
                    $availData = Table_availability::findOne(['file_name' => $linkInfo->file_name]);
                    if ($availData !== null) {
                        $file = Yii::getAlias('@conclusionsDirectory') . '\\' . $linkInfo->file_name;
                        $fileName = 'МРТ ' . $availData->execution_area . ' ' . $availData->patient_name . '.pdf';
                    }
                }
            }
            else{
                Telegram::sendDebug("Попытка скачать файл незарегистрированного пользователя: $linkInfo->execution_id");
            }
            //$linkInfo->delete();
            if (!empty($file) && !empty($fileName) && is_file($file)) {
                // отдам файл на выгрузку
                Yii::$app->response->sendFile($file, $fileName);
                return;
            }
            else{
                Telegram::sendDebug("Файл не найден: $file");
            }
        }
        else{
            Telegram::sendDebug("Попытка скачать файл по несуществующей ссылке: $link");
        }
        // страница не найдена, видимо, ссылка истекла
        throw new NotFoundHttpException('Не удалось найти файлы по данной ссылке, видимо, они удалены по истечению срока давности. Вы можете обратиться к нам за повторной публикацией файлов');
    }
}
