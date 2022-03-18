<?php

namespace app\models\database;

use app\priv\Info;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property int $id [bigint(20) unsigned]
 * @property string $area_name [varchar(255)]
 */
class Archive_execution_area extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'execution_area';
    }

    public static function getDb(): Connection
    {
        return Yii::$app->db1;
    }

    /**
     * @return Archive_execution_area[]
     */
    public static function getAreasArray(): array
    {
        $answer = [];
        $all = self::find()->all();
        if (!empty($all)) {
            foreach ($all as $item) {
                $answer[] = $item->area_name;
            }
        }
        return $answer;
    }
}