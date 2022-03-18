<?php


namespace app\models\database;


use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property string $receiver_id [varchar(255)]
 * @property int $timestamp [int(11)]
 * @property string $text
 * @property int $message_token [int(25)]
 */

class ViberMessaging extends ActiveRecord
{
    public static function tableName():string
    {
        return 'viber_messages';
    }
}