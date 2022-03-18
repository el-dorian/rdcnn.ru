<?php


namespace app\models;


use app\models\database\Reviews;
use app\models\utils\MailSettings;
use Exception;
use Yii;

class Rate
{

    public static function handleReview($id): void
    {
        if (Reviews::haveNoReview($id)) {
            $review = Yii::$app->request->post('reviewArea');
            $text = "Пользователь {$id} оставил отзыв: " . $review;
            Telegram::sendDebug($text);
            self::sendRate($text);
            Reviews::addReview($id, Yii::$app->request->post('reviewArea'));
        }
    }

    public static function handleRate(string $id, string $rate): void
    {
        // проверю, не было ли ещё отзыва
        if (Reviews::haveNoRate($id)) {
            Reviews::addRate($id, $rate);
            $text = "Пользователь {$id} оценил нашу работу на {$rate}";
            Telegram::sendDebug($text);
            self::sendRate($text);
        }
    }

    private static function sendRate($text): void
    {
        try {
            // получу настройки почты
            $settingsFile = Yii::$app->basePath . '\\priv\\mail_settings.conf';
            $content = file_get_contents($settingsFile);
            $settingsArray = mb_split("\n", $content);
            if (count($settingsArray) === 3) {
                // отправлю письмо
                $mail = Yii::$app->mailer->compose()
                    ->setFrom([MailSettings::getInstance()->address => 'Наши оценки'])
                    ->setSubject('Получен новый отзыв')
                    ->setHtmlBody($text)
                    ->setTo(['eldorianwin@gmail.com' => 'eldorianwin@gmail.com']);
                // попробую отправить письмо, в случае ошибки- вызову исключение
                $mail->send();
            }
        } catch (Exception $e) {

        }

    }
}