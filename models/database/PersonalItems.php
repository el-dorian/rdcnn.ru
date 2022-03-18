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
 * @property string $name [varchar(255)]
 * @property string $access_token [varchar(255)]
 * @property string $login [varchar(255)]
 * @property string $pass_hash [varchar(255)]
 * @property string $email [varchar(255)]
 * @property string $phone [char(12)]
 * @property string $role [int(11)]
 */
class PersonalItems extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'personal_items';
    }
}