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
 * @property string $address [varchar(255)]
 * @property string $text
 * @property string $title [varchar(255)]
 * @property string $theme [varchar(255)]
 * @property string $name [varchar(255)]
 */
class MailingSchedule extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'mailing_schedule';
    }
}