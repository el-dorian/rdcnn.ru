<?php


namespace app\models\database;


use app\models\Table_availability;
use app\models\User;
use app\models\utils\MailSettings;
use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * @property int $id [int(10) unsigned]
 * @property string $review [text]
 * @property string $patient_id [varchar(11)]
 * @property int $rate [int(10) unsigned]
 */
class Reviews extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'reviews';
    }

    public static function haveNoRate(string $id)
    {
        return self::find()->where(['patient_id' => $id])->andWhere(['not', ['rate' => null]])->count();
    }

    public static function haveNoReview(string $id)
    {
        return self::find()->where(['patient_id' => $id])->andWhere(['not', ['review' => null]])->count();
    }

    public static function addRate(string $id, string $rate): void
    {
        $existent = self::findOne(['patient_id' => $id]);
        if ($existent === null) {
            $existent = new self(['patient_id' => $id]);
        }
        $existent->rate = (int)$rate;
        $existent->save();
    }

    public static function addReview($id, string $review): void
    {
        $existent = self::findOne(['patient_id' => $id]);
        if ($existent === null) {
            $existent = new self(['patient_id' => $id]);
        }
        $existent->review = Yii::$app->db->quoteValue($review);
        $existent->save();
    }
}