<?php

namespace app\models;

use chillerlan\QRCode\QRCode;
use Yii;
use yii\base\Exception;

class LoginQr
{
    private ?User $user;

    /**
     * @param User|null $user
     */
    public function __construct(?User $user)
    {
        $this->user = $user;
    }

    /**
     * @throws Exception
     */
    public static function generateQrFile($executionId)
    {
        $execution = User::findByUsername($executionId);
        if ($execution !== null) {
            $entity = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/tmp';
            if (!is_dir($entity) && !is_dir($entity) && !mkdir($entity) && !is_dir($entity)) {
                echo(sprintf('Directory "%s" was not created', $entity));
            }
            $fileName = "$entity/" . Yii::$app->security->generateRandomString();
            (new QRCode)->render('https://rdcnn.ru/enter/' . $execution->access_token, $fileName);
            if (is_file($fileName)) {
                return $fileName;
            }
        }
        return null;
    }

    public function getStringQr(): ?string
    {
        if ($this->user !== null) {
            return 'https://rdcnn.ru/enter/' . $this->user->access_token;
        }
        return null;
    }
}