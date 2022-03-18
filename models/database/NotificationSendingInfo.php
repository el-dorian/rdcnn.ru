<?php


namespace app\models\database;


use app\models\Table_availability;
use app\models\Telegram;
use app\models\User;
use app\models\utils\MailSettings;
use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * @property string $execution_id [varchar(255)]
 * @property bool $is_conc [tinyint(1)]
 * @property bool $is_exec [tinyint(1)]
 * @property bool $sent [tinyint(1)]
 * @property int $person_id [int(11)]
 * @property string $address [varchar(255)]
 * @property string $name [varchar(255)]
 * @property int $create_time [int(10) unsigned]
 */
class NotificationSendingInfo extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'notification_sending_info';
    }

    /**
     * @return NotificationSendingInfo[]
     */
    public static function getWaiting(): array
    {
        return self::findAll(['sent' => 0]);
    }

    public function notify(): void
    {
        $availabilityInfo = Table_availability::findAll(['file_name' => $this->name, 'file_create_time' => $this->create_time]);
        if(!empty($availabilityInfo)){
            foreach ($availabilityInfo as $item) {
                $item->is_notification_sent = 1;
                $item->save();
            }
        }
    }
}