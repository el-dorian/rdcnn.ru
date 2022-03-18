<?php


namespace app\models;


use yii\db\ActiveRecord;

/**
 * @property string $item_name [varchar(64)]
 * @property string $user_id [varchar(64)]
 * @property int $created_at [int(11)]
 */

class Table_auth_assigment extends ActiveRecord
{
    public static function tableName()
    {
        return 'auth_assignment';
    }
}