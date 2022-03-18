<?php


namespace app\models\utils;


use app\models\Telegram;
use Exception;
use RuntimeException;
use TelegramBot\Api\InvalidArgumentException;
use Yii;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

class MyErrorHandler
{

    /**
     * Обработка ошибок
     * @param Exception $e
     */
    public static function sendError(Exception $e): void
    {
        $root = Yii::$app->basePath;
        $errorInfo = '';
        $errorInfo .= TimeHandler::timestampToDateTime(time()) . "\r\n";
        $errorInfo .= 'url ' . Url::to() . "\r\n";
        $errorInfo .= 'message ' . $e->getMessage() . "\r\n";
        $errorInfo .= 'code ' . $e->getCode() . "\r\n";
        $errorInfo .= 'in file ' . $e->getFile() . "\r\n";
        $errorInfo .= 'in sting ' . $e->getLine() . "\r\n";
        $errorInfo .= $e->getTraceAsString() . "\r\n";
        if (!empty($_POST)) {
            $errorInfo .= 'post is ';
            $errorInfo .= self::arrayToString($_POST);
        }
        if (!empty($_GET)) {
            $errorInfo .= 'get is ';
            $errorInfo .= self::arrayToString($_GET);
        }
        // Помещу данные об ошибке в файл
        if (!is_dir($root . '/errors') && !mkdir($concurrentDirectory = $root . '/errors') && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        // пишу ошибки в файл только если учётка не админская
        file_put_contents($root . '/errors/' . 'errors.txt', $errorInfo . "\r\n\r\n\r\n");
        // отправлю ошибки асинхронно
        //self::asyncSendErrors();
        if(!($e instanceof NotFoundHttpException)){
            Telegram::sendDebug($errorInfo);
        }
    }

    /**
     * Массив в строку
     * @param $arr
     * @return string
     */
    public static function arrayToString($arr): string
    {
        $answer = '';
        if (!empty($arr)) {
            foreach ($arr as $key => $value) {
                if (is_array($value)) {
                    $val = self::arrayToString($value);
                    $answer .= "\r\n\t $key => $val";
                } else {
                    $answer .= "\r\n\t $key => $value";
                }
            }
        }
        return $answer;
    }

    public static function sendErrors(): void
    {
        // тут отправлю письмо
        // получу настройки почты
        $settingsFile = Yii::$app->basePath . '\\priv\\mail_settings.conf';
        if (is_file($settingsFile)) {
            // получу данные
            $content = file_get_contents($settingsFile);
            $settingsArray = mb_split("\n", $content);
            if (count($settingsArray) === 3) {
                // получу текст письма
                $errorFile = Yii::$app->basePath . '\\errors\\errors.txt';
                if (is_file($errorFile)) {
                    $text = MailHandler::getMailText(file_get_contents($errorFile));
                    // отправлю письмо
                    $mail = Yii::$app->mailer->compose()
                        ->setFrom([$settingsArray[0] => 'Ошибки сервера РДЦ'])
                        ->setSubject('Найдены новые ошибки')
                        ->setHtmlBody($text)
                        ->setTo(['eldorianwin@gmail.com' => 'Мне']);
                    // попробую отправить письмо, в случае ошибки- вызову исключение
                    $mail->send();
                    // удалю файл с ошибками
                    unlink($errorFile);
                }
            }
        } else {
            echo 'no mail settings file';
        }
    }

    private static function asyncSendErrors(): void
    {
        $file = Yii::$app->basePath . '\\yii.bat';
        if (is_file($file)) {
            $command = "$file console/send-errors";
            $outFilePath = Yii::$app->basePath . '/logs/mail_info_file.log';
            $outErrPath = Yii::$app->basePath . '/logs/mail_info_err.log';
            $command .= ' > ' . $outFilePath . ' 2>' . $outErrPath . ' &';
            try {
                // попробую вызвать процесс асинхронно
                $handle = new \COM('WScript.Shell');
                $handle->Run($command, 0, false);
            } catch (Exception $e) {
                try {
                    exec($command);
                } catch (Exception $e) {
                }
            }
        }
    }
}