<?php


namespace app\models\database;


use app\models\Table_availability;
use app\models\User;
use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property int $execution_id [int(11)]
 * @property string $link [varchar(255)]
 * @property string $file_type [enum('execution', 'conclusion')]
 * @property string $file_name [varchar(255)]
 */

class TempDownloadLinks extends ActiveRecord
{
    public static function tableName():string
    {
        return 'temp_download_links';
    }

    /**
     * @param User $execution
     * @param string $type
     * @param string|null $filename
     * @return TempDownloadLinks
     * @throws Exception
     */
    public static function createLink(User $execution, string $type, string $filename = null): TempDownloadLinks
    {
        $link = Yii::$app->security->generateRandomString(255);
        if($type === 'execution'){
            $link = new self(['file_name' => $execution->username . '.zip', 'file_type' => 'execution', 'link' => $link, 'execution_id' => $execution->id]);
            $link->save();
            return $link;
        }
        if($type === 'conclusion'){
            $link = new self(['file_name' => $filename, 'file_type' => 'conclusion', 'link' => $link, 'execution_id' => $execution->id]);
            $link->save();
            return $link;
        }
        throw new Exception("Неизвестный тип файла");
    }

    /**
     * @throws Exception
     */
    public static function createFileLink(Table_availability $item, User $user): TempDownloadLinks
    {
        $link = Yii::$app->security->generateRandomString(255);
        if($item->is_execution){
            $link = new self(['file_name' => $item->userId . '.zip', 'file_type' => 'execution', 'link' => $link, 'execution_id' => $user->id]);
            $link->save();
            return $link;
        }
        if($item->is_conclusion){
            $link = new self(['file_name' => $item->file_name, 'file_type' => 'conclusion', 'link' => $link, 'execution_id' => $user->id]);
            $link->save();
            return $link;
        }
        throw new Exception("Неизвестный тип файла");
    }

    public static function executionLinkExists(string $username): int
    {
        return self::find()->where(['file_type' => 'execution', 'execution_id' => $username])->count();
    }

    /**
     * @param Table_availability $item
     * @param User $user
     * @return string
     * @throws Exception
     */
    public static function getLink(Table_availability $item, User $user): string
    {
        $existent = self::findOne(['file_name' => $item->file_name]);
        if($existent !== null){
            return $existent->link;
        }

        return self::createFileLink($item, $user)->link;
    }

    /**
     * @throws Exception
     */
    public static function getArchiveFileLink(Archive_complex_execution_info $archiveExecution, int $executionId): string
    {
        $existentLink = self::findOne(['file_name' => $archiveExecution->pdf_path, 'file_type' => 'archive', 'execution_id' => $executionId]);
        if($existentLink !== null){
            return $existentLink->link;
        }
        $link = Yii::$app->security->generateRandomString(255);
        $link = new self(['file_name' => $archiveExecution->pdf_path, 'file_type' => 'archive', 'link' => $link, 'execution_id' => $executionId]);
        $link->save();
        return $link->link;
    }

}