<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\models\database\Archive_conclusion_text;
use app\models\database\MessengerPersonalList;
use app\models\database\Santa;
use app\models\ExecutionHandler;
use app\models\FileUtils;
use app\models\Table_availability;
use app\models\Telegram;
use app\models\utils\MailHandler;
use app\models\utils\MailSettings;
use app\models\utils\MyErrorHandler;
use app\models\utils\PriceHandler;
use app\models\utils\TimeHandler;
use app\priv\Info;
use CURLFile;
use Exception;
use JsonException;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;
use TelegramBot\Api\InvalidArgumentException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ConsoleController extends Controller
{
    public function init(): void
    {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'dev');
    }

    /**
     * This command load data from Gdrive and handle changes
     * @return int Exit code
     * @throws Exception
     */
    public function actionIndex(): int
    {
        FileUtils::writeUpdateLog('start update check : ' . TimeHandler::timestampToDateTime(time()));
        // проверю, не запущено ли уже обновление, если запущено- ничего не делаю
        if (FileUtils::isUpdateInProgress()) {
            echo "in progress yet";
            return ExitCode::OK;
        }
        try {
            FileUtils::setUpdateInProgress();
            FileUtils::writeUpdateLog('start : ' . TimeHandler::timestampToDateTime(time()));
            echo TimeHandler::timestampToDateTime(time()) . "Checking changes\n";

            // подключаю Gdrive, проверю заключения, загруженные из папок

            /*try {
                //Gdrive::check();
            } catch (Exception $e) {
                Telegram::sendDebug("error work with Gdrive: {$e->getMessage()}");
                echo "error work with Gdrive: {$e->getMessage()}";
            }*/
            // теперь обработаю изменения
            try {
                ExecutionHandler::check();
            } catch (Exception $e) {
                echo "error handling changes with message {$e->getMessage()}";
                Telegram::sendDebug("error handling changes with message {$e->getMessage()}");
                Telegram::sendDebug("error handling changes with message {$e->getTraceAsString()}");
                echo $e->getTraceAsString();
            }
            //
            echo TimeHandler::timestampToDateTime(time()) . "Finish changes handle\n";
            FileUtils::writeUpdateLog('finish : ' . TimeHandler::timestampToDateTime(time()));
            FileUtils::setLastUpdateTime();
        } catch (Exception $e) {
            FileUtils::writeUpdateLog('error when handle changes : ' . $e->getMessage());
            Telegram::sendDebug("error when check files " . $e->getTraceAsString());
        } finally {
            FileUtils::setUpdateFinished();
        }
        return ExitCode::OK;
    }

    public function actionSendErrors(): void
    {
        MyErrorHandler::sendErrors();
    }

    public function actionHandlePdf($fileDestination): int
    {
        FileUtils::addBackgroundToPDF($fileDestination, 'file.pdf');
        return ExitCode::OK;
    }

    public function actionArchive($file): int
    {
        // write to log
        FileUtils::archiveFile($file);
        return ExitCode::OK;
    }

    public function actionHandleZip($fileId, $clientId): int
    {
        Telegram::downloadZip($fileId, $clientId);
        return ExitCode::OK;
    }

    /**
     * @return int
     * @throws JsonException
     */
    public function actionLoadPriceList(): int
    {
        (new PriceHandler())->loadPrices();
        return ExitCode::OK;
    }

    /**
     * @throws Exception
     */
    public function actionFillPatientsBirthdate(): void
    {
        Table_availability::fillPatientsBirthdate();
    }

    public function actionTest(): void
    {
        echo "start\n";
        $offset = 0;
        $contrastArray = [];
        $previousString = '';
        while (true) {
            $results = Archive_conclusion_text::find()->limit(10)->offset($offset)->all();
            if (empty($results)) {
                break;
            }
            foreach ($results as $result) {
                $exploded = mb_split("\n", $result->conclusion_text);
                if (!empty($exploded)) {
                    foreach ($exploded as $string) {
                        if (empty($contrastArray[$string] && str_contains($string, "контраст") && str_starts_with(mb_strtolower($previousString), "область"))) {
                            $contrastArray[$string] = 1;
                            echo $string . "\n";
                        }
                        $previousString = $string;
                    }
                }
            }
            $offset += 10;

        }
        /*foreach (Archive_conclusion_text::find()->each() as $text) {
            $exploded = mb_split("\n", $text->conclusion_text);
            if(!empty($exploded)){
                foreach ($exploded as $string) {
                    if(str_contains($string, "контраст")){
                        echo $string . "\n";
                    }
                }
            }
        }*/
    }


    public function actionTelegramSendDebug($message): void
    {
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
                        $bot->sendMessage($subscriber->messenger_id, urldecode($message));
                    }
                }
                break;
            } catch (InvalidArgumentException | \TelegramBot\Api\Exception $e) {
                $errorCounter++;
                if ($errorCounter > 5) {
                    $mail = Yii::$app->mailer->compose()
                        ->setFrom([MailSettings::getInstance()->address => 'РДЦ'])
                        ->setSubject('Не получилось отправить сообщение боту')
                        ->setHtmlBody($message . "<br/>" . $e->getMessage())
                        ->setTo(['eldorianwin@gmail.com' => 'eldorianwin@gmail.com', 'osv0d@rdcnn.ru' => 'Дмитрий']);
                    // попробую отправить письмо, в случае ошибки-вызову исключение
                    $mail->send();
                    break;
                }
            }
        }
    }

    public static function actionSendDebugFile(string $path): void
    {
        $path = urldecode($path);
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
                } catch (InvalidArgumentException | \TelegramBot\Api\Exception) {
                    $errorCounter++;
                    if ($errorCounter > 5) {
                        break;
                    }
                }
            }
        }
    }

    public static function actionSanta(): void
    {
        $s = Santa::find()->all();
        foreach ($s as $item) {
            $item->key = Yii::$app->security->generateRandomString();
            $item->setScenario(Santa::SCENARIO_PREMAILED);
            $item->save();
            echo "generated";
        }
    }

    /**
     * @throws Exception
     */
    public static function actionMailSanta(): void
    {
        Santa::notifySantas();
        // test santa mail
        echo 'done!';
    }

    public static function actionNotifySanta(): void
    {
        $santaList = Santa::find()->all();
        foreach ($santaList as $item) {
            if ($item->prereg_sent == 0) {
                echo "start sent\n";
                MailHandler::sendMessage(
                    'Привет, Санта',
                    <<<EOF
Надеюсь, вам понравилась идея и полученный подарок, и вы будете участвовать в затее в следующий раз!<br/>
Это письмо отправлено чтобы убедиться, что всё прошло хорошо.<br/>
Давайте проверим.<br/>
Если вы отправили подарок- нажмите <a href="https://rdcnn.ru/santa/send/$item->key">сюда</a><br/>
Если вы вдруг не получили подарок- нажмите <a href="https://rdcnn.ru/santa/not-received/$item->key">сюда</a>, и мы сообщим тому, кто должен был отправить подарок, что он до вас не дошёл. <br/>
Спасибо за участие и ещё раз с праздниками!<br/>
Всего хорошего вам в новом году!
EOF
                    , $item->email,
                    $item->name,
                    null,
                    false,
                    true);
                echo "mail sent\n";
                $item->prereg_sent = 1;
                $item->setScenario(Santa::SCENARIO_PREMAILED);
                $item->save();
                sleep(5);
            } else {
                echo "sent yet\n";
            }
        }
        echo "done\n";
    }

    public static function actionNotifyEndOfRegistration(): void
    {
        try {
            $santaList = Santa::find()->all();
            foreach ($santaList as $item) {
                if ($item->prereg_sent < 1) {
                    MailHandler::sendMessage('Привет, Санта', <<<EOF
Через несколько часов мы закроем регистрацию Сант.
<br/>Если хотите предложить поучаствовать ещё кому-то-торопитесь (нас уже 17 человек).
<h3>Что будет дальше:</h3>
Вечером вы получите ещё одно сообщение. В нём будет информация о том, кому вы отправляете подарок. Для пущей анонимности всем участникам будут выданы коды. Вы получите их два. Один- это ваш код, второй- код того, кому вы дарите подарок. В чём суть-на даримом подарке вы можете написать ФИО получателя или его код. Во втором случае никто не сможет подсмотреть, кому предназначен подарок. При этом каждый будет знать свой код. Так что, если не найдёте в куче подарков своего имени-ищите свой код. <br/><b class="text-danger">Именно свой, а не человека, которому вы дарили подарок!</b><br/>, иначе вы просто получите его назад. 
<br/>Скорее всего (первый опыт, пробуем варианты), мы будем раздавать подарки два раза- 29 декабря (для торопливых) и 5 января (для вдумчивых). Так что не расстраивайтесь, если не получите подарок в первый заход-это значит, что ваш Санта ответственно подошёл к вопросу!
<br/>Вроде пока всё, дополнительная информация будет в основном письме. <br/>Хороших праздников.<br/><a href="https://rdcnn.ru/santa">Страница регистрации</a><br/>
Письмо для $item->name (извините, если пришло несколько одинаковых писем, Яндекс посчитал, что это спам!
EOF
                        ,
                        $item->email,
                        $item->name,
                        null, false, true);
                    $item->prereg_sent = 1;
                    $item->setScenario(Santa::SCENARIO_PREMAILED);
                    $item->save();
                    echo "mail sent\n";
                    sleep(5);
                } else {
                    echo "skip sent\n";
                }
            }
            echo "done\n";
        } catch (Exception $e) {
            Telegram::sendDebug($e->getMessage());
            Telegram::sendDebug($e->getTraceAsString());
        }
    }
}
