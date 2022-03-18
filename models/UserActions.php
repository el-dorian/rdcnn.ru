<?php


namespace app\models;


use Throwable;
use Yii;
use yii\base\Model;
use yii\db\StaleObjectException;

class UserActions extends Model
{
    /**
     * @param $executionNumber
     * @throws Throwable
     * @throws StaleObjectException
     */
    public static function deleteUser($executionNumber): void
    {
        if(!empty($executionNumber)){
            // получу данные о пользователе
            $execution = User::findByUsername($executionNumber);
            if($execution !== null){
                $file = Yii::getAlias('@conclusionsDirectory') . '\\' . $execution->username . '.pdf';
                if(is_file($file)){
                    unlink($file);
                }
                $file = Yii::getAlias('@executionsDirectory') . '\\' . $execution->username . '.zip';
                // проверю, если есть файл результатов сканирования-выдам его на загрузку
                if(is_file($file)){
                    unlink($file);
                }
                $execution->delete();
            }
        }
    }
}