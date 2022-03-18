<?php /** @noinspection PhpUndefinedClassInspection */


namespace app\models\utils;


use COM;
use Exception;

class ComHandler
{
    /**
     * <p>Отправка команды на исполнение командной строкой</p>
     * @param string $command
     */
    public static function runCommand(string $command): void
    {
        try {
            // попробую вызвать процесс асинхронно
            $handle = new COM('WScript.Shell');
            /** @noinspection PhpUndefinedMethodInspection */
            $handle->Run($command, 0, false);
        } catch (Exception $e) {
            exec($command);
        }
    }
}