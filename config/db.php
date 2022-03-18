<?php

use app\priv\Info;
use yii\db\Connection;

require_once dirname(__DIR__) . '/priv/Info.php';

return [
    'class' => Connection::class,
    'dsn' => 'mysql:host=localhost;dbname=rdcnn',
    'username' => Info::DB_LOGIN,
    'password' => Info::DB_PASSWORD,
    'charset' => 'utf8',
];