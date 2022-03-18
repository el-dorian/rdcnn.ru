<?php

namespace app\models\utils;

use DateTime;

class TimeHandler
{
    public static array $months = ['Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря',];

    /**
     * Получу метку времени начала сегдняшнего дня
     *
     */
    public static function getTodayStart(): int
    {
        $dtNow = new DateTime();
        $dtNow->modify('today');
        return $dtNow->getTimestamp();
    }

    /**
     * Возвращает дату и время из временной метки
     * @param int $timestamp
     * @return string
     */
    public static function timestampToDateTime(int $timestamp): string
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $answer = '';
        $day = $date->format('d');
        $answer .= $day;
        $month = mb_strtolower(self::$months[$date->format('m') - 1]);
        $answer .= ' ' . $month . ' ';
        $answer .= $date->format('Y') . ' года.';
        $answer .= $date->format(' H:i:s');
        return $answer;
    }

    /**
     * Возвращает дату и время из временной метки
     * @param int $timestamp
     * @return string
     */
    public static function timestampToDate(int $timestamp): string
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $answer = '';
        $day = $date->format('d');
        $answer .= $day;
        $month = mb_strtolower(self::$months[$date->format('m') - 1]);
        $answer .= ' ' . $month . "\n";
        $answer .= $date->format('Y') . ' года.';
        return $answer;
    }

    public static function archiveDateToDate(string $execution_date): string
    {
        $arr = mb_split("-", $execution_date);
        return $arr[2] . " " . mb_strtolower(self::$months[$arr[1] - 1]) . "\n" . $arr[0] . " года";
    }
}