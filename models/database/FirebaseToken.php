<?php


namespace app\models\database;


use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property string $token [varchar(255)]
 * @property string $user [int(11)]
 * @property string $last_command [varchar(500)]  Последняя отправленная команда
 * @property int $working_now [tinyint(1)]  Работает ли сейчас
 */
class FirebaseToken extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'firebase_binding';
    }

    public static function add(int $id, $firebaseToken)
    {
        $existent = self::findOne(['token' => $firebaseToken]);
        if($existent !== null){
            $existent->delete();
        }
        $new = new self();
        $new->user = $id;
        $new->token = $firebaseToken;
        $new->save();
    }
}