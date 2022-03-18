<?php


namespace app\models\database;


use app\models\utils\GrammarHandler;
use app\models\utils\MailHandler;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property string $name [varchar(255)]
 * @property string $email [varchar(255)]
 * @property string $baby [int(10) unsigned]
 * @property string $required_place [int(10) unsigned]
 * @property string $mark_as_received [int(10) unsigned]
 * @property string $mark_as_send [int(10) unsigned]
 * @property string $prereg_sent [int(10) unsigned]
 * @property string $key [varchar(255)]
 */
class Santa extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'santas';
    }

    public const SCENARIO_REGISTER = 'register';
    public const SCENARIO_PREMAILED = 'premailed';

    public static function countSantas(): int
    {
        return self::find()->count();
    }


     #[ArrayShape([self::SCENARIO_REGISTER => "string[]", self::SCENARIO_PREMAILED => "string[]"])] public function scenarios(): array
    {
        return [
            self::SCENARIO_REGISTER => ['name', 'email'],
            self::SCENARIO_PREMAILED => ['id', 'name', 'email', 'baby', 'required_place', 'mark_as_received', 'mark_as_read', 'prereg_sent', 'key'],
        ];
    }

    /**
     * @return array the validation rules.
     */
    public function rules(): array
    {
        return [
            // username and password are both required
            [['name', 'email'], 'required', 'on' => self::SCENARIO_REGISTER],
            [['id', 'name', 'email', 'baby', 'required_place', 'mark_as_received', 'mark_as_read', 'prereg_sent', 'key'], 'safe', 'on' => self::SCENARIO_PREMAILED],
        ];
    }

    #[ArrayShape(['name' => "string", 'email' => "string"])] public function attributeLabels(): array
    {
        return [
            'name' => 'Фамилия имя и отчество',
            'email' => 'Ваша электронная почта',
        ];
    }

    public function register(): bool
    {
        // check existent
        if (self::find()->where(['email' => $this->email])->orWhere(['name' => trim($this->name)])->count() > 0) {
            $this->addError('name', 'Похоже, вы уже регистрировались тут!');
            return false;
        }
        $clearEmail = mb_strtolower(trim($this->email));
        if (!filter_var($clearEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', 'Проверьте адрес электронной почты, похоже, он неверный :(');
            return false;
        }
        $this->save();
        MailHandler::sendMessage('Привет, Санта', "<h1>Добрый день, " . GrammarHandler::handlePersonals($this->name) . ".</h1>. <h2>Теперь ты- тайный Санта!</h2> <p>Спасибо за регистрацию.<br/>Теперь ожидай. 25 декабря ты получишь имя человека, которому нужно будет сделать подарок. На подарке нужно будет написать имя или идентификатор получателя (его ты получишь в письме 25 числа), и положить в волшебную коробку, местоположение которой будет дано в том же письме. Более подробные инструкции будут даны позднее. Добро пожаловать в игру!</p>.", $this->email, $this->name, null, false, true);
        return true;
    }

    /**
     * @throws Exception
     */
    private static function assign_users($users_array): array
    {
        $givers = $users_array;
        $receivers = $users_array;
        //Foreach giver
        /** @var Santa $user */
        foreach ($givers as $uid => $user) {
            $not_assigned = true;
            //While a user hasn't been assigned their secret santa
            while ($not_assigned) {
                //Randomly pick a person for the user to buy for
                $choice = random_int(0, count($receivers) - 1);
                //If randomly picked user is NOT themselves
                if ($user->email !== $receivers[$choice]->email) {
                    //Assign the user the randomly picked user
                    $givers[$uid]->baby = $receivers[$choice]->id;
                    //And remove them from the list
                    unset($receivers[$choice]);
                    //Correct array
                    $receivers = array_values($receivers);
                    //exit loop
                    $not_assigned = false;
                } else if (count($receivers) === 1) {
                    //Swap with someone else (in this case the first guy who got assigned.
                    //Steal first persons, person and give self to them.
                    $givers[$uid]->baby = $givers[0]->baby;
                    $givers[0]->baby = $givers[$uid]->id;
                    $not_assigned = false;
                }
            }
        }
        //Return array of matched users
        return $givers;
    }

    /**
     * Validate Array
     * Ensure array is safe to use in Secret Santa Script
     * @param Santa[] Array
     * @return true if safe.
     */
    private static function validateArray($users_array): bool
    {
        //Ensure that more than 2 users have been provided
        if (count($users_array) < 2) {
            echo '[Error] A minimum of 2 secret santa participants is required in order to use this system.';
            return false;
        }
        //Check there are no duplicate emails
        $tmp_emails = array();
        $tmp_names = array();
        /** @var Santa $u */
        foreach ($users_array as $u) {
            if (in_array($u->email, $tmp_emails, true)) {
                echo "[Error] Users cannot share an email or be in the secret santa more than once.\n";
                return false;
            }
            $tmp_emails[] = $u->email;
            if (in_array($u->name, $tmp_names, true)) {
                echo "[Error] Users cannot share an name or be in the secret santa more than once.\n";
                return false;
            }
            $tmp_names[] = $u->name;
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public static function assign(): void
    {
        $santaArray = self::find()->all();
        if (self::validateArray($santaArray)) {
            echo "list validated!\n";
            // assign
            $givers = self::assign_users($santaArray);
            echo "givers received!\n";
            echo "givers count is " . count($givers) . "\n";
            /** @var Santa $giver */
            foreach ($givers as $giver) {
                $giver->setScenario(Santa::SCENARIO_PREMAILED);
                $giver->save();
                echo "$giver->id to $giver->baby\n";
            }
            return;
        }
        echo "list invalid!\n";
    }
    /**
     * @throws Exception
     */
    public static function notifySantas(): void
    {
        $santaArray = self::find()->all();
        if (self::validateArray($santaArray)) {
            echo "list validated!\n";
            // assign
            $givers = self::assign_users($santaArray);
            echo "givers received!\n";
            echo "givers count is " . count($givers) . "\n";
            /** @var Santa $giver */
            foreach ($givers as $giver) {
                $giver->setScenario(self::SCENARIO_PREMAILED);
                $receiver = self::findOne($giver->baby);
                if($receiver !== null){
                    $receiverName = ucwords($receiver->name);
                    if($giver->prereg_sent > 0){
                        MailHandler::sendMessage('Привет, Санта', <<<EOF
Пришло время узнать, кому вы дарите подарок.
<h3>Ваш идентификатор: $giver->id</h3>
<h4>Выше- ваш идентификатор. Подарок для вас может быть подписан как вашим именем, так и вашим идентификатором</h4>
<p>Теперь, наконец, данные человека, которому вы дарите подарок.</p>
<h2>Идентификатор получателя: $receiver->id</h2>
<h2>ФИО получателя: $receiverName</h2>
<p>Контейнеры для подарков будут установлены в каждом центре. Также, думаю, наладим трансфер подарков между центрами чуть попозже</p>
<p>Спасибо за участие, пусть ваш подарок будет волшебным!</p>
EOF
                            ,
                            $giver->email,
                            $giver->name,
                            null, false, true);
                        $giver->prereg_sent = 0;
                        $giver->save();
                        echo "mail sent\n";
                        sleep(5);
                    }
                    else{
                        echo "skip sent\n";
                    }
                }
                else{
                    echo "receiver not found!!";
                }
            }
            return;
        }
        echo "list invalid!\n";
    }
}