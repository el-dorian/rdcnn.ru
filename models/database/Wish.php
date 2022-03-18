<?php


namespace app\models\database;


use app\models\Telegram;
use app\models\utils\GrammarHandler;
use app\models\utils\MailHandler;
use JetBrains\PhpStorm\ArrayShape;
use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property string $wish [string]
 */
class Wish extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'wishes';
    }

    #[ArrayShape([self::SCENARIO_REGISTER => "string[]"])] public function scenarios(): array
    {
        return [
            self::SCENARIO_REGISTER => ['wish'],
        ];
    }

    /**
     * @return array the validation rules.
     */
    public function rules(): array
    {
        return [
            // username and password are both required
            [['wish'], 'safe', 'on' => self::SCENARIO_REGISTER],
        ];
    }

    #[ArrayShape(['wish' => "string"])] public function attributeLabels(): array
    {
        return [
            'wish' => 'Загадайте желание',
        ];
    }

    public const SCENARIO_REGISTER = 'register';

    public function register(): bool
    {
        $wish = addslashes(mb_strtolower(trim($this->wish)));
        if(self::find()->where(['wish' => $wish])->count() < 1){
            $this->wish = $wish;
            $this->save();
        }
        Telegram::sendDebug("кто-то загадал желание :)");
        //MailHandler::sendMessage('Привет, Санта', "<h1>Добрый день, " . GrammarHandler::handlePersonals($this->name) . ".</h1>. <h2>Теперь ты- тайный Санта!</h2> <p>Спасибо за регистрацию.<br/>Теперь ожидай. 25 декабря ты получишь имя человека, которому нужно будет сделать подарок. На подарке нужно будет написать имя или идентификатор получателя (его ты получишь в письме 25 числа), и положить в волшебную коробку, местоположение которой будет дано в том же письме. Более подробные инструкции будут даны позднее. Добро пожаловать в игру!</p>.", $this->email, $this->name, null, false, true);
        return true;
    }
}