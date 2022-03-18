<?php

namespace app\models\database;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property int $id [bigint(20) unsigned]
 * @property string $doc_name [varchar(255)]
 */
class Archive_doctor extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'doctor';
    }

    /**
     * @throws InvalidConfigException
     */
    public static function getDb(): Connection
    {
        return Yii::$app->db1;
    }

    /**
     * @return Archive_doctor[]
     */
    public static function getDoctorsArray(): array
    {
        $answer = [];
        $all = self::find()->all();
        if(!empty($all)){
            foreach ($all as $item) {
                $answer[] = $item->doc_name;
            }
        }
        return $answer;
    }
}