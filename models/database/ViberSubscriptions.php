<?php


namespace app\models\database;


use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property string $viber_id [varchar(255)]  Идентификатор вайбера
 * @property int $patient_id [int(11)]  Идентификатор обследования
 */

class ViberSubscriptions extends ActiveRecord
{
    public static function tableName():string
    {
        return 'viber_subscriptions';
    }
}