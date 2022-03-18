<?php

namespace app\models\database;

use app\priv\Info;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property int $id [int(10) unsigned]
 * @property string $agent [varchar(255)]
 */
class Archive_contrast extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'contrast_agent';
    }

    public static function getDb(): Connection
    {
        return Yii::$app->db1;
    }
}