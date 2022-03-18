<?php

namespace app\models\database;

use app\models\utils\GrammarHandler;
use app\priv\Info;
use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property int $id [bigint(20) unsigned]
 * @property string $patient_name [varchar(255)]
 * @property string $birthdate [date]
 * @property string $sex [enum('м', 'ж', '-')]
 */
class Archive_patient extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'patient';
    }


    public static function getDb(): Connection
    {
        return Yii::$app->db1;
    }

    public static function normalizeBirthdate(): void
    {
        /** @var Archive_patient $person */
        foreach (self::find()->each() as $person) {
            echo "handle $person->patient_name\n";
            $oldBirthdate = $person->birthdate;
            try {
                $newBirthdate = GrammarHandler::normalizeDate($oldBirthdate);
                if ($oldBirthdate !== $newBirthdate) {
                    $person->birthdate = $oldBirthdate;
                    $person->save();
                    echo "changed $oldBirthdate on $newBirthdate\n";
                }
            } catch (Exception $e) {
                echo "Неверный формат даты: " . $e->getMessage() . "\n";
            }
        }
    }
}