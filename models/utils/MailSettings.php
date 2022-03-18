<?php


namespace app\models\utils;

use yii\base\Model;

class MailSettings extends Model
{
    private static $instance;

    public static function getInstance():MailSettings
    {
        if(empty(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public $user_pass;
    public $user_name;
    public $address;


    public function attributeLabels():array
    {
        return [
            'address' => 'Адрес почты',
            'user_pass' => 'Пароль',
            'user_name' => 'Логин',
        ];
    }

    /**
     * @return array
     */
    public function rules():array
    {
        return [
            // name, email, subject и body атрибуты обязательны
            [['address', 'user_pass', 'user_name'], 'required'],
        ];
    }

    private function __construct()
    {
        parent::__construct();
        // прочитаю настройки из файла
        $file = dirname($_SERVER['DOCUMENT_ROOT'] . './/') . '/priv/mail_settings.conf';
        if (!is_file($file)) {
            // создаю файл
            file_put_contents($file, "test\ntest\ntest\ntest\n\n");
        }
        $content = file_get_contents($file);
        $settingsArray = mb_split("\n", $content);
        $this->address = $settingsArray[0];
        $this->user_name = $settingsArray[1];
        $this->user_pass = $settingsArray[2];
    }
}