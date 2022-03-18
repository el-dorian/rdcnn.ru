<?php


namespace app\models;


use yii\db\ActiveRecord;

/**
 * @property int $id [bigint(20) unsigned]  Глобальный идентификатор
 * @property string $type [enum('execution', 'conclusion')]
 * @property string $file_name [varchar(255)]
 * @property string $link [varchar(255)]
 * @property int $execution_number [int(11)]
 */

class Table_viber_download_links extends ActiveRecord
{
    public static function tableName():string
    {
        return 'viber_download_links';
    }

    public static function getConclusionLinks($name){
        // проверю наличие заключений по выбранному обследованию
        FileUtils::getConclusions($name);
    }
}