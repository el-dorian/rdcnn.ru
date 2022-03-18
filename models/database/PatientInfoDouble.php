<?php


namespace app\models\database;


use app\models\Table_availability;
use app\models\User;
use app\models\utils\GrammarHandler;
use app\models\utils\MailSettings;
use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * @property int $id [int(10) unsigned]
 * @property string $name [varchar(255)]
 * @property string $phone [varchar(255)]
 * @property string $sex [varchar(255)]
 * @property string $email [varchar(255)]
 * @property string $unsubscribe_token [char(255)]
 * @property bool $unsubscribed [tinyint(1)]
 */
class PatientInfoDouble extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'patients_base_clear';
    }
}