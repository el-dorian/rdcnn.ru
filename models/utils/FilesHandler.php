<?php


namespace app\models\utils;


use app\models\ExecutionHandler;
use app\models\FileUtils;
use app\models\Telegram;
use app\priv\Info;
use RuntimeException;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\web\UploadedFile;
use ZipArchive;

class FilesHandler extends Model
{

    public static function handleDroppedFile(UploadedFile $file): void
    {
        echo $file->extension . "\n";
        // для начала - проверю тип файла
        switch ($file->extension) {
            case 'pdf':
            case 'doc':
            case 'docx':
                // сохраню файл во временную папку
                $savedFile = self::saveTempFile($file);
                try {
                    FileUtils::handleFileUpload($savedFile);
                } catch (Exception $e) {
                } finally {
                    try {
                        unlink($savedFile);
                    } catch (\Exception $e) {
                    }
                }
                break;
            case 'zip':
                self::saveZip($file);
        }
    }

    private static function saveTempFile(UploadedFile $file): string
    {
        $root = Yii::$app->basePath;
        // создам временную папку, если её ещё не существует
        if (!is_dir($root . '/temp') && !mkdir($concurrentDirectory = $root . '/temp') && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        $fileName = $root . "/temp/" . Yii::$app->security->generateRandomString() . '.' . $file->extension;
        $file->saveAs($fileName);
        return $fileName;
    }

    private static function saveZip(UploadedFile $file): void
    {
        // сохраняю файл во временную папку
        $root = Yii::$app->basePath;
        // создам временную папку, если её ещё не существует
        if (!is_dir($root . '/temp') && !mkdir($concurrentDirectory = $root . '/temp') && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        do {
            $dirName = Yii::$app->security->generateRandomString();
            $filePath = $root . "/temp/" . $dirName;
        } while (is_file($filePath));
        $file->saveAs($filePath);
        self::unzip($filePath);
    }

    /**
     * @param string $dir
     * @return string|null
     */
    public static function handleDicomDir(string $dir): ?string
    {
        if (str_starts_with($dir, Info::EXEC_FOLDER . '\\.')) {
            Telegram::sendDebug("try to handle root dir $dir");
            return null;
        }
        if (is_dir($dir)) {
            // проверю, есть ли в папке DICOMDIR-файл. Если нет- папка левая, удалю её
            $dicomdirDest = $dir . DIRECTORY_SEPARATOR . 'DICOMDIR';
            if (is_file($dicomdirDest)) {
                // получу содержимое файла
                $content = file_get_contents($dicomdirDest);
                // уберу все непечатные символы
                $clearedContent = GrammarHandler::clearText($content);
                // теперь найду в этом бардаке номер обследования
                $executionNumber = GrammarHandler::findExecutionNumber($clearedContent);
                if ($executionNumber !== null) {
                    // добавляю в папку необходимый софт и добавляю её в ЛК
                    ExecutionHandler::packFiles($executionNumber, $dir);
                    return $executionNumber;
                }
            }
            // если папка не содержит файл DICOMDIR- удалю её
            Telegram::sendDebug("Found dir without dicom files $dicomdirDest");
            ExecutionHandler::rmRec($dir);
        }
        return null;
    }

    /**
     * @param string $file
     * @return string|null
     * @throws Exception
     */
    public static function unzip(string $file): ?string
    {
        $executionNumber = null;
        if (is_file($file)) {
            echo 'have file';
            $root = Yii::$app->basePath;
            // создам временную папку, если её ещё не существует
            if (!is_dir($root . '/temp') && !mkdir($concurrentDirectory = $root . '/temp') && !is_dir($concurrentDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
            do {
                $dirName = Yii::$app->security->generateRandomString();
                $filePath = $root . "/temp/" . $dirName;
            } while (is_dir($filePath));
            $zip = new ZipArchive;
            if ($zip->open($file) === TRUE) {
                $zip->extractTo($filePath);
                $zip->close();

                // теперь найду корневую папку. Она может быть на директорию ниже, чем распакованная
                $dirContent = array_slice(scandir($filePath), 2);
                if (count($dirContent) === 1) {
                    $executionNumber = self::handleDicomDir($filePath . DIRECTORY_SEPARATOR . $dirContent[0]);
                } else {
                    $executionNumber = self::handleDicomDir($filePath);
                }
            }
            // удалю временную папку
            FileUtils::removeDir($filePath);
            // удалю исходный файл
            unlink($file);
        }
        return $executionNumber;
    }
}