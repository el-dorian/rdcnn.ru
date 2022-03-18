<?php

namespace app\models\database;

use app\models\Table_availability;
use app\models\User;
use app\priv\Info;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property int $id [bigint(20) unsigned]
 * @property string $execution_number [char(7)] Номер обследования
 * @property string $execution_date [date] Время обследования
 * @property int $patient [bigint(20)]
 * @property int $doctor [bigint(20)]
 * @property int $execution_area [bigint(20)]
 * @property int $contrast [bigint(20)]
 * @property string $path [varchar(1000)]  Путь к PDF
 * @property int $text [bigint(20)]
 * @property string $md5 [char(32)]
 */
class Archive_execution extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'execution';
    }


    public static function getDb(): Connection
    {
        return Yii::$app->db1;
    }

    /**
     * @param User $user
     * @return Archive_execution[]|null
     */
    public static function getPreviousForUser(User $user): ?array
    {
        // check user personals and birthdate
        $personals = Table_availability::getPatientName($user->username);
        $birthdate = Table_availability::getPatientBirthdate($user->username);
        if ($personals !== null && $birthdate !== null) {
            $patient = Archive_patient::findOne(['patient_name' => $personals, 'birthdate' => $birthdate]);
            if ($patient !== null) {
                $executions = self::find()->where(['patient' => $patient->id])->orderBy('execution_date')->all();
                if (!empty($executions)) {
                    return $executions;
                }
            }
        }
        return null;
    }

    public static function findInArchive(string $request): string
    {
        // проверю, является ли переданная строка номером обследования
        $searchPattern = '/^A?\d+$/';
        if (preg_match($searchPattern, $request)) {
            $info = Archive_complex_execution_info::findAll(['execution_id' => $request]);
            return self::extractItem($info, $request);
        }
        $searchPattern = '/^[\w\s-]+$/u';
        if(preg_match($searchPattern, $request)){
            $info = Archive_complex_execution_info::find()->where(['like', 'patient_name', "$request%", false])->all();
            return self::extractItem($info, $request);
        }
        $searchPattern = '/^([\w\s-]+)\s*,\s*(\d{2}.\d{2}.\d{4})$/u';
        if(preg_match($searchPattern, $request, $matches)){
            $birthdate = str_replace(".", "-", $matches[2]);
            $info = Archive_complex_execution_info::find()->where(['like', 'patient_name', "$matches[1]%", false])->andWhere(['birth_date' => $birthdate])->all();
            return self::extractItem($info, $request);
        }
        return "Не понимаю, что вы хотите найти по запросу $request. Расскажите Сергею, что вы хотели найти и он скажет, что не так :)";
    }

    /**
     * @param array $info
     * @param string $request
     * @return string
     */
    public static function extractItem(array $info, string $request): string
    {
        if (!empty($info)) {
            $answer = "Результаты поиска: \n";
            foreach ($info as $item) {
                $answer .= "==========\n";
                $answer .= "$item->execution_area\n";
                $answer .= "$item->execution_date\n";
                $answer .= "Скачать /adl_$item->execution_identifier\n";
            }
            return $answer;
        }
        return "Обследование с номером $request не найдено";
    }
}