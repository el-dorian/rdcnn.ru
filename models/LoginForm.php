<?php

namespace app\models;

use app\models\utils\GrammarHandler;
use JetBrains\PhpStorm\ArrayShape;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\db\StaleObjectException;

/**
 * LoginForm is the model behind the login form.
 *
 * @property User|null $user This property is read-only.
 *
 */
class LoginForm extends Model
{
    public const SCENARIO_ADMIN_LOGIN = 'admin_login';
    public const SCENARIO_USER_LOGIN = 'user_login';


    #[ArrayShape([self::SCENARIO_ADMIN_LOGIN => "string[]", self::SCENARIO_USER_LOGIN => "string[]"])] public function scenarios(): array
    {
        return [
            self::SCENARIO_ADMIN_LOGIN => ['username', 'password'],
            self::SCENARIO_USER_LOGIN => ['username', 'password'],
        ];
    }

    public ?string $username = null;
    public ?string $password = null;

    private ?User $_user = null;


    /**
     * @return array the validation rules.
     */
    public function rules(): array
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required', 'on' => self::SCENARIO_USER_LOGIN],
            [['username', 'password'], 'required', 'on' => self::SCENARIO_ADMIN_LOGIN],
        ];
    }

    #[ArrayShape(['username' => "string", 'password' => "string"])] public function attributeLabels(): array
    {
        return [
            'username' => 'Номер обследования',
            'password' => 'Пароль',
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     */
    public function validatePassword(string $attribute): void
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if ($user !== null) {
                // проверю, если было больше 5 неудачных попыток ввода пароля- время между попытками должно составлять не меньше 10 минут
                if ($user->failed_try > 3 && $user->last_login_try > time() - 600) {
                    Telegram::sendDebug("too many wrong try for $user->username ($user->failed_try)");
                    $this->addError($attribute, 'Слишком много неверных попыток ввода пароля. Должно пройти не менее 10 минут с последней попытки');
                    return;
                }
                if ($user->failed_try > 10) {
                    Telegram::sendDebug("Try access blocked account $user->username");
                    $this->addError($attribute, 'Учётная запись заблокирована. Обратитесь к администратору для восстановления доступа');
                    return;
                }

                if (!$user->validatePassword(trim($this->$attribute))) {
                    $user->last_login_try = time();
                    $user->failed_try = ++$user->failed_try;
                    Telegram::sendDebug("User access wrong try $user->username ($user->failed_try)");
                    $user->save();
                } else {
                    return;
                }
            }
            Telegram::sendDebug("try to login with wrong $user->username" . $this->$attribute);
            $this->addError($attribute, 'Неверный номер обследования или пароль');
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * @return bool whether the user is logged in successfully
     * @throws Exception
     */
    public function login(): bool
    {
        if ($this->validate()) {
            $user = $this->getUser();
            if (null === $user) {
                throw new Exception('Не найден пользователь!');
            }
            $user->failed_try = 0;
            if (!$user->access_token) {
                $user->access_token = Yii::$app->getSecurity()->generateRandomString(255);
            }
            $user->save();
            return Yii::$app->user->login($this->getUser());
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User
     */
    public function getUser(): User
    {
        if ($this->_user === null) {
            $this->_user = User::findByUsername(trim($this->username));
        }
        return $this->_user;
    }

    /**
     * @return bool
     */
    public function loginAdmin(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $blocked = $this->checkBlacklist();
        if ($blocked) {
            $blocked->last_try = time();
            $blocked->save();
            $this->addError('password', 'Компьютер в чёрном списке. Напишите или позвоните Сергею :) (Но скорее всего, он уже в курсе) !');
            Telegram::sendDebug("$ip : Попытка входа в админку при том, что IP в чёрном списке с данными {$this->username} : {$this->password}");
            return false;
        }

        // получу админа
        /** @var User $admin */
        $admin = User::getAdmin();

        // проверю, правильно ли введено имя
        if ($admin->username !== trim($this->username)) {
            $this->registerWrongTry();
            $this->addError('password', 'Неверный логин или пароль');
            Telegram::sendDebug("$ip : Попытка входа администратора с неверными данными\nЛогин: {$this->username}");
            return false;
        }

        if (!$admin->validatePassword(trim($this->password))) {
            $this->registerWrongTry();
            $this->addError('password', 'Неверный логин или пароль');
            Telegram::sendDebug("$ip : Попытка входа администратора с неверными данными\nПароль: {$this->password}");
            return false;
        }

// логиню пользователя
        if (empty($admin->access_token)) {
            try {
                $admin->access_token = Yii::$app->getSecurity()->generateRandomString(255);
            } catch (Exception $e) {
                Telegram::sendDebug("$ip : Не удалось добавить токен администратора {$e->getTraceAsString()}");
                die('не удалось добавить токен');
            }
        }
        $admin->save();
        Telegram::sendDebug("$ip : Успешный вход в админку");
        return Yii::$app->user->login($admin, 2678400);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function loginUser(): bool
    {
        // проверю наличие логина и пароля
        if (empty($this->username) && empty($this->password)) {
            $this->addError('username', 'Нужно ввести номер обследования и пароль!');
            return false;
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        // проверю, не занесён ли IP в чёрный список
        $blocked = $this->checkBlacklist();
        if ($blocked) {
            // если прошло больше суток с последнего ввода пароля-уберу IP из блеклиста
            if (time() - $blocked->last_try > 60 * 60 * 24) {
                try {
                    $blocked->delete();
                    Telegram::sendDebug("$ip : удалён из чёрного списка по таймауту");
                } catch (StaleObjectException | Throwable $e) {
                    Telegram::sendDebug("$ip : ошибка удаления из ЧС {$e->getTraceAsString()}");
                }
            } // если количество неудачных попыток больше 3 и не прошло 10 минут-отправим ожидать
            elseif ($blocked->try_count > 3 && (time() - $blocked->last_try < 600)) {
                $this->addError('username', 'Слишком много неверных попыток ввода пароля. Должно пройти не менее 10 минут с последней попытки');
                Telegram::sendDebug("$ip : Слишком много неверных попыток ввода пароля {$this->username} : {$this->password}");
                return false;
            } elseif ($blocked->missed_execution_number > 20) {
                $this->addError('username', 'Слишком много попыток ввода номера обследования. Попробуйте снова через сутки');
                Telegram::sendDebug("$ip : Слишком много неверных попыток входа. Адрес заблокирован на сутки {$this->username} : {$this->password}");
                return false;
            }
        }
        // проверю, не производится ли попытка зайти под админской учёткой
        /** @var User $admin */
        $admin = User::getAdmin();
        if ($this->username === $admin->username) {
            $this->addError('password', 'Неверный номер обследования или пароль');
            Telegram::sendDebug("$ip : Попытка зайти в клиентскую часть с админскими данными {$this->username} : {$this->password}");
            return false;
        }

        // получу данные о пользователе
        $user = User::findByUsername(GrammarHandler::toLatin($this->username));
        if ($user !== null) {
            if ($user->status === User::USER_DEACTIVATED) {
                return false;
            }
            if ($user->failed_try > 20) {
                $this->addError('username', 'Было выполнено слишком много неверных попыток ввода пароля. В целях безопасности данные были удалены. Вы можете обратиться к нам для восстановления доступа');
                Telegram::sendDebug("$ip : Слишком много неверных попыток ввода пароля и учётная запись заблокирована {$this->username} : {$this->password}");
                return false;
            }
            if (!$user->validatePassword(trim($this->password))) {
                $user->last_login_try = time();
                $user->failed_try = ++$user->failed_try;
                $user->save();
                $this->addError('username', 'Неверный номер обследования или пароль');
                if ($blocked !== null) {
                    $blocked->try_count = ++$blocked->try_count;
                    $blocked->last_try = time();
                    $blocked->save();
                    Telegram::sendDebug("$ip : Попытка входа с неверными данными {$this->username} : {$this->password}");
                } else {
                    $this->registerWrongTry();
                    Telegram::sendDebug("$ip : Попытка входа в клиентскую часть с неверными данными {$this->username} : {$this->password}");
                }
                return false;
            }
            // логиню пользователя
            $user->failed_try = 0;
            if (empty($user->access_token)) {
                $user->access_token = Yii::$app->getSecurity()->generateRandomString(255);
            }
            $user->save();
            return Yii::$app->user->login($user);
        }
        // проверю номер обследования, если он устарел-оповещу об этом
        if (User::isObsolete(GrammarHandler::toLatin($this->username))) {
            $this->addError('username', 'Неверный номер обследования или пароль. Возможно, прошло больше 5 дней с момента обследования, в этом случае ваши данные удалены из личного кабинета в целях безопасности. Вы можете обратиться к нам за повторным добавлением данных.');
        } else {
            $this->addError('username', 'Неверный номер обследования или пароль');
        }
        Telegram::sendDebug("$ip : Попытка входа в клиентскую часть с неверными данными {$this->username} : {$this->password}");
        // добавлю пользователя в список подозрительных
        if ($blocked) {
            $blocked->missed_execution_number = ++$blocked->missed_execution_number;
            $blocked->try_count = ++$blocked->try_count;
            $blocked->last_try = time();
            $blocked->save();
        } else {
            $this->registerWrongTry();
        }
        return false;
    }


    private function checkBlacklist(): ?Table_blacklist
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        return Table_blacklist::findOne(['ip' => $ip]);
    }

    private function registerWrongTry(): void
    {
        // проверю, не занесён ли уже IP в базу данных
        $ip = $_SERVER['REMOTE_ADDR'];
        $is_blocked = Table_blacklist::findOne(['ip' => $ip]);
        if ($is_blocked === null) {
            // внесу IP в чёрный список
            $blacklist = new Table_blacklist();
            $blacklist->ip = $ip;
            $blacklist->try_count = 1;
            $blacklist->last_try = time();
            $blacklist->save();
            Telegram::sendDebug("$ip : Первая попытка входа с неверными данными: {$this->username} : {$this->password}");
        } else {
            $is_blocked->try_count = ++$is_blocked->try_count;
            $is_blocked->save();
        }
    }
}
