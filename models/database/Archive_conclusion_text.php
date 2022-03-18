<?php

namespace app\models\database;

use app\priv\Info;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property int $id [bigint(20) unsigned]
 * @property string $conclusion_text
 */
class Archive_conclusion_text extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conclusion_full_text';
    }

    public static function getDb(): Connection
    {
        return Yii::$app->db1;
    }
}