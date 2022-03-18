<?php

namespace app\models\database;

use app\priv\Info;
use JetBrains\PhpStorm\ArrayShape;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Connection;


/**
 * @property string $execution_number [char(7)]  Номер обследования
 * @property string $execution_date [date]  Время обследования
 * @property int $execution_identifier [bigint(20) unsigned]
 * @property string $pdf_path [varchar(1000)]  Путь к PDF
 * @property string $patient_name [varchar(255)]
 * @property string $patient_birthdate [date]
 * @property string $execution_area [varchar(255)]
 * @property string $doctor [varchar(255)]
 * @property string $contrast_info [varchar(255)]
 */
class Archive_complex_execution_info extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'execution_info';
    }


    public static function getDb(): Connection
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return Yii::$app->db1;
    }


    /**
     * @param string $executionId
     * @param string $patientPersonals
     * @param string $executionDateStart
     * @param string $executionDateFinish
     * @param string $doctor
     * @param string $area
     * @param string $fullTextSearch
     * @param string $birthdate
     * @return array
     */
    #[ArrayShape(['status' => "int", 'executions' => "\app\models\database\SearchExecutionInfo[]"])]
    public static function request(string $executionId,
                                   string $patientPersonals,
                                   string $executionDateStart,
                                   string $executionDateFinish,
                                   string $doctor,
                                   string $area,
                                   string $fullTextSearch,
                                   string $birthdate): array
    {
        // todo добавить полнотекстовый поиск

        $request = self::find();
        if (!empty($executionId)) {
            $request->andWhere(['execution_number' => $executionId]);
        }
        if (!empty($patientPersonals)) {
            $request->andWhere(['like', 'patient_name', "$patientPersonals%", false]);
        }
        if (!empty($birthdate)) {
            $request->andWhere(['patient_birthdate' => $birthdate]);
        }
        if (!empty($doctor)) {
            $request->andWhere(['doctor' => $doctor]);
        }
        if(!empty($executionDateStart)){
            if(!empty($executionDateFinish)){
                $request->andWhere([">=", 'execution_date', $executionDateStart]);
                $request->andWhere(["<=", 'execution_date', $executionDateFinish]);
            }
            else{
                $request->andWhere(['execution_date' => $executionDateStart]);
            }
        }
        if (!empty($area)) {
            $request->andWhere(['execution_area' => $area]);
        }
        return ['status' => 1, 'executions' => $request->limit(1000)->all()];
    }

    public static function countPreviousExecutions(string $patientName, string $birthdate):int
    {
        return self::find()->where(['patient_name' => $patientName, 'patient_birthdate' => $birthdate])->count();
    }

    public static function restoreConclusions($executionNumber): void
    {
        $conclusions = self::findAll(['execution_number' => $executionNumber]);
        if(!empty($conclusions)){
            foreach ($conclusions as $conclusion) {
                if(file_exists(Info::ARCHIVE_PATH . DIRECTORY_SEPARATOR . $conclusion->pdf_path)){
                    copy(Info::ARCHIVE_PATH . DIRECTORY_SEPARATOR . $conclusion->pdf_path, Info::CONC_FOLDER . DIRECTORY_SEPARATOR . $conclusion->execution_number . '_' . $conclusion->execution_area . '.pdf');
                }
            }
        }
    }
}