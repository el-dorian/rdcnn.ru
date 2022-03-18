<?php


namespace app\models\utils;


use app\models\FileUtils;
use app\models\Telegram;
use Exception;
use Yii;
use yii\base\Model;

class Management extends Model
{
    public static function handleChanges()
    {
        // проверю, не изменилась ли версия ПО, если изменилась- пришлю сообщение в телеграм-бот об этом
        if(FileUtils::isSoftwareVersionChanged()){
            Telegram::sendDebug("Изменилась версия по: " . FileUtils::getSoftwareVersion());
        }

        // если обновление не в ходу и с последнего обновления прошло больше 10 минут- запущу его
        if (!FileUtils::isUpdateInProgress() && FileUtils::getLastUpdateTime() < (time() - 30)) {
            $file = Yii::$app->basePath . '\\yii.bat';
            if (is_file($file)) {
                $command = "$file console";
                $outFilePath = Yii::$app->basePath . '/logs/content_change.log';
                $outErrPath = Yii::$app->basePath . '/logs/content_change_err.log';
                $command .= ' > ' . $outFilePath . ' 2>' . $outErrPath . ' &"';
                try {
                    // попробую вызвать процесс асинхронно
                    /** @noinspection PhpUndefinedClassInspection */
                    /** @noinspection PhpFullyQualifiedNameUsageInspection */
                    $handle = new \COM('WScript.Shell');
                    /** @noinspection PhpUndefinedMethodInspection */
                    $handle->Run($command, 0, false);
                    return true;
                } catch (Exception $e) {
                    exec($command);
                    return true;
                }
            }
        } else {
            // запишу в файл отчётов, что ещё не пришло время для проверки
            return 'timeout ' . (60 - (time() - FileUtils::getLastUpdateTime()));
        }
        return false;
    }

    /**
     * @param string $file
     */
    public static function startScript(string $file): void
    {
        if (is_file($file)) {
            $command = $file . ' ' . Yii::$app->basePath;
            $outFilePath = Yii::$app->basePath . '\\logs\\update_file.log';
            $outErrPath = Yii::$app->basePath . '\\logs\\update_err.log';
            $command .= ' > ' . $outFilePath . ' 2>' . $outErrPath . ' &"';
            ComHandler::runCommand($command);
            Telegram::sendDebug('Запущено обновление ПО через GitHub');
        }
    }

    /**
     *<b>Обновлю ПО сервера через GITHUB</b>
     */
    public static function updateSoft(): void
    {
        // отмечу время проверки обновления
        FileUtils::setLastCheckUpdateTime();
        $file = Yii::$app->basePath . '\\updateFromGithub.bat';
        self::startScript($file);
    }
}