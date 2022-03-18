<?php


namespace app\models\database;


use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property int $get_errors [tinyint(1)]
 * @property string $messenger_id [varchar(255)]
 * @property string $last_message_text [varchar(500)]
 * @property int $work_state [tinyint(1)]
 */
class MessengerPersonalList extends ActiveRecord
{
    public const WORK_AURORA = 1;
    public const WORK_NV = 2;
    public const NO_WORK = 0;

    public static function tableName(): string
    {
        return 'messenger_personal_list';
    }

    /**
     * @param $receiverId
     */
    public static function register($receiverId): void
    {
        if (null === self::findOne(['viber_id' => $receiverId])) {
            (new self(['viber_id' => $receiverId]))->save();
        }
    }

    /**
     * Проверю, работает ли у нас собеседник
     * @param $receiverId
     * @return bool
     */
    public static function iWorkHere($receiverId): bool
    {
        return (bool)self::find()->where(['messenger_id' => $receiverId])->count();
    }

    public static function subscribeGetErrors(int $getId): void
    {
        $data = self::findOne(['messenger_id' => $getId]);
        if ($data !== null && $data->get_errors === 0) {
            $data->get_errors = 1;
            $data->save();
        }
    }

    public static function setWork(string $personId, int $state): void
    {
        $person = self::findOne(['messenger_id' => $personId]);
        if ($person !== null) {
            $person->work_state = $state;
            $person->save();
        }
    }

    public static function setLastCommand(string $personId, ?string $command): void
    {
        $person = self::findOne(['messenger_id' => $personId]);
        if ($person !== null) {
            $person->last_message_text = $command;
            $person->save();
        }
    }

    public static function getLastCommand($personId): ?string
    {
        $person = self::findOne(['messenger_id' => $personId]);
        if ($person !== null) {
            return $person->last_message_text;
        }
        return null;
    }

    /**
     * Возвращу всех, кто зарегистрирован как находящийся на работе в данном центре
     * @param int $state
     * @return MessengerPersonalList[]
     */
    public static function getWorkers(int $state): array
    {
        return self::findAll(['work_state' => $state]);
    }
}