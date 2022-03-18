<?php


namespace app\models\database;


use app\models\Table_availability;
use app\models\User;
use app\models\utils\MailSettings;
use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * @property int $id [int(10) unsigned]
 * @property string $role [varchar(255)]
 */
class PersonalRoles extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'personal_roles';
    }
}