<?php


namespace app\models\database;


use yii\db\ActiveRecord;
/**
 * @property string $item_name [varchar(64)]
 * @property string $user_id [varchar(64)]
 * @property int $created_at [int(11)]
 */

class AuthAssignment extends ActiveRecord
{


    public static function tableName():string
    {
        return 'auth_assignment';
    }
}