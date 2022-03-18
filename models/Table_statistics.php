<?php


namespace app\models;


use app\models\utils\TimeHandler;
use yii\db\ActiveRecord;

/**
 * @property int $id [bigint(20) unsigned]  Глобальный идентификатор
 * @property string $type [enum('download_conclusion', 'download_execution', 'print_conclusion')]
 * @property string $user_id [varchar(255)]
 * @property int $timestamp [int(10) unsigned]
 */
class Table_statistics extends ActiveRecord
{
    public static function tableName():string
    {
        return 'statistics';
    }

    public static function plusConclusionDownload($userId): void
    {
        $newCounter = new self(['user_id' => $userId, 'type' => 'download_conclusion', 'timestamp' => time()]);
        $newCounter->save();
    }

    public static function plusExecutionDownload($userId): void
    {
        $newCounter = new self(['user_id' => $userId, 'type' => 'download_execution', 'timestamp' => time()]);
        $newCounter->save();
    }

    public static function plusConclusionPrint($userId): void
    {
        $newCounter = new self(['user_id' => $userId, 'type' => 'print_conclusion', 'timestamp' => time()]);
        $newCounter->save();
    }

    /**
     * Верну в текстовом формате статистику по использованию ЛК
     * @return string
     */
    public static function getFullState(): string
    {
        $answer = '';
        // получу общее количество скачанных заключений
        $answer .= 'Всего загружено заключений: ' . self::getTotalConclusionsCount() . "\n";
        $answer .= 'Всего распечатано заключений: ' . self::getTotalConclusionsPrintCount() . "\n";
        $answer .= 'Всего скачано файлов: ' . self::getTotalExecutionsCount() . "\n";
        $answer .= 'Загружено заключений сегодня: ' . self::getTodayConclusionsCount() . "\n";
        $answer .= 'Распечатано заключений сегодня: ' . self::getTodayConclusionsPrintCount() . "\n";
        $answer .= 'Скачано файлов сегодня: ' . self::getTodayExecutionsCount() . "\n";
        return $answer;
    }

    /**
     * @return int
     */
    public static function getTotalConclusionsCount():int
    {
        return self::find()->where(['type' => 'download_conclusion'])->count();
    }

    public static function getTotalExecutionsCount(): int
    {
        return self::find()->where(['type' => 'download_execution'])->count();
    }
    public static function getTotalConclusionsPrintCount(): int
    {
        return self::find()->where(['type' => 'print_conclusion'])->count();
    }

    private static function getTodayConclusionsCount()
    {
        return self::find()->where(['type' => 'download_conclusion'])->andWhere(['>', 'timestamp', TimeHandler::getTodayStart()])->count();
    }
    private static function getTodayConclusionsPrintCount()
    {
        return self::find()->where(['type' => 'print_conclusion'])->andWhere(['>', 'timestamp', TimeHandler::getTodayStart()])->count();
    }
    private static function getTodayExecutionsCount()
    {
        return self::find()->where(['type' => 'download_execution'])->andWhere(['>', 'timestamp', TimeHandler::getTodayStart()])->count();
    }
}