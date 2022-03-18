<?php

namespace app\models;

use app\priv\Info;
use Exception;
use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 *
 * @property int $id [int(10) unsigned]
 * @property string $username [varchar(255)]  Номер обследования
 * @property string $auth_key [varchar(255)]
 * @property string $password_hash [varchar(255)]  Хеш пароля
 * @property int $status [smallint(6)]  Статус пользователя
 * @property int $created_at [int(11)]  Дата регистрации
 * @property int $updated_at [int(11)]
 * @property bool $failed_try [tinyint(4)]  Неудачных попыток входа
 * @property string $access_token [varchar(255)]
 * @property string $authKey
 * @property int $last_login_try [bigint(20)]  Дата последней попытки входа
 */
class User extends ActiveRecord implements IdentityInterface
{

    public const ADMIN_NAME = 'adfascvlgalsegrlkuglkbaldasdf';
    public const USER_ACTIVE = 1;
    public const USER_DEACTIVATED = 0;
    public const USER_ARCHIVED = 2;

    // имя таблицы
    public static function tableName(): string
    {
        return 'person';
    }

    /**
     * Finds an identity by the given ID.
     * @param string|int $id the ID to be looked for
     * @return User the identity object that matches the given ID.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentity($id): ?User
    {
        return static::findOne($id);
    }

    /**
     * Finds an identity by the given token.
     * @param mixed $token the token to be looked for
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * @return User the identity object that matches the given token.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentityByAccessToken($token, $type = null): ?User
    {
        return self::findOne(['access_token' => $token]);
    }

    /**
     * @param $username
     * @return User|null
     */
    public static function findByUsername($username): ?User
    {
        // найти по имени администратора невозможно
        if ($username !== self::ADMIN_NAME) {
            return static::find()->where(['username' => $username])->one();
        }
        return null;
    }

    public static function getAdmin()
    {
        return static::find()->where(['username' => self::ADMIN_NAME])->one();
    }

    /**
     * @return User[]
     * @throws Exception
     */
    public static function findAllRegistered(): array
    {
        // верну все записи кроме админской

        // если есть ограничение по времени- использую его

        if (Utils::isTimeFiltered()) {
            $startOfInterval = Utils::getStartInterval();
            $endOfInterval = Utils::getEndInterval();
            return static::find()->where(['<>', 'username', self::ADMIN_NAME])->andWhere(['>', 'updated_at', $startOfInterval])->andWhere(['<', 'created_at', $endOfInterval])->orderBy('created_at DESC')->all();
        }
        return static::find()->where(['<>', 'username', self::ADMIN_NAME])->andWhere(['>', 'updated_at', time() - Info::DATA_SAVING_TIME])->orderBy('created_at DESC')->all();
    }

    public static function getLast($center)
    {
        $nextExecutionId = '';
        // получу последнего зарегистрированного пациента
        if ($center === 'aurora') {
            $last = self::find()->where(['like', 'username', 'A%', false])->orderBy('created_at DESC')->one();
            if ($last === null) {
                $nextExecutionId = 'A1';
            } else {
                $nextExecutionId = $last->username;
            }
        }
        if ($center === 'ct') {
            $last = self::find()->where(['like', 'username', 'T%', false])->orderBy('created_at DESC')->one();
            if ($last === null) {
                $nextExecutionId = 'T1';
            } else {
                $nextExecutionId = $last->username;
            }
        } else if ($center === 'nv') {
            $last = self::find()->where(['regexp', 'username', '^[0-9]'])->orderBy('created_at DESC')->one();
            if ($last === null) {
                $nextExecutionId = '1';
            } else {
                $nextExecutionId = $last->username;
            }
        }
        return $nextExecutionId;
    }

    public static function getNext($username): int|string
    {
        if (str_starts_with($username, 'A')) {
            $num = substr($username, 1);
            return 'A' . ++$num;
        }
        if (str_starts_with($username, 'T')) {
            $num = substr($username, 1);
            return sprintf("T%05d", ++$num);
        }
        return ++$username;
    }

    public static function findRegistered(string $center, int $startOfInterval, int $endOfInterval): array
    {
        if ($center === 'a') {
            return static::find()->where(['<>', 'username', self::ADMIN_NAME])->andWhere(['like', 'username', 'A%', false])->andWhere(['>', 'created_at', $startOfInterval])->andWhere(['<', 'created_at', $endOfInterval])->orderBy('created_at DESC')->all();
        }
        return static::find()->where(['<>', 'username', self::ADMIN_NAME])->andWhere(['like', 'username', '[0-9]%', false])->andWhere(['>', 'created_at', $startOfInterval])->andWhere(['<', 'created_at', $endOfInterval])->orderBy('created_at DESC')->all();
    }

    public static function registerIfNot(string $username, string $pass): bool
    {
        if (self::find()->where(['username' => $username])->count()) {
            return false;
        }
        $newUser = new self();
        $newUser->username = $username;
        $newUser->password_hash = Yii::$app->security->generatePasswordHash($pass);
        $newUser->created_at = time();
        $auth_key = Yii::$app->getSecurity()->generateRandomString(32);
        $newUser->auth_key = $auth_key;
        $newUser->access_token = Yii::$app->getSecurity()->generateRandomString(255);
        $newUser->status = 1;
        $newUser->save();
        return true;
    }

    public static function isObsolete(string $username): bool
    {
        $filteredOne = $username;
        $firstRegistered = 0;
        // получу последнего зарегистрированного пациента
        if (str_starts_with(mb_strtolower($username), 'a')) {
            $filteredOne = substr($username, 1);
            $last = self::find()->where(['like', 'username', 'A%', false])->orderBy('created_at DESC')->one();
            if ($last !== null) {
                $firstRegistered = substr($last->username, 1);
            } else {
                Telegram::sendDebug("not found last");
            }
        } else if (str_starts_with(mb_strtolower($username), 't')) {
            $filteredOne = substr($username, 1);
            $last = self::find()->where(['like', 'username', 'T%', false])->orderBy('created_at DESC')->one();
            if ($last !== null) {
                $firstRegistered = substr($last->username, 1);
            }
        } else {
            $last = self::find()->where(['regexp', 'username', '^[0-9]'])->orderBy('created_at DESC')->one();
            if ($last !== null) {
                $firstRegistered = substr($last->username, 1);
            }
        }
        return (int)$filteredOne < (int)$firstRegistered;
    }

    public static function enableAccount($executionNumber)
    {
        $user = self::findByUsername($executionNumber);
        if ($user !== null) {
            $user->updated_at = time();
            $user->save();
            return true;
        }
        return false;
    }

    /**
     * @throws \yii\base\Exception
     */
    public static function changePass($executionNumber): ?string
    {
        $user = self::findByUsername($executionNumber);
        if ($user !== null) {
            $pass = self::generateNumericPassword();
            $user->password_hash = Yii::$app->security->generatePasswordHash($pass);
            $user->save();
            return $pass;
        }
        return null;
    }

    public static function findActive(): array
    {
        return self::find()->where(['status' => 1])->all();
    }

    public function validatePassword($password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Returns an ID that can uniquely identify a user identity.
     * @return string|int an ID that uniquely identifies a user identity.
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Returns a key that can be used to check the validity of a given identity ID.
     *
     * The key should be unique for each individual user, and should be persistent
     * so that it can be used to check the validity of the user identity.
     *
     * The space of such keys should be big enough to defeat potential identity attacks.
     *
     * This is required if [[User::enableAutoLogin]] is enabled. The returned key will be stored on the
     * client side as a cookie and will be used to authenticate user even if PHP session has been expired.
     *
     * Make sure to invalidate earlier issued authKeys when you implement force user logout, password change and
     * other scenarios, that require forceful access revocation for old sessions.
     *
     * @return string a key that is used to check the validity of a given identity ID.
     * @see validateAuthKey()
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * Validates the given auth key.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @param string $authKey the given auth key
     * @return bool whether the given auth key is valid.
     * @see getAuthKey()
     */
    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key === $authKey;
    }

    public static function generateNumericPassword(): string
    {
        $chars = array_merge(range(0, 9));
        shuffle($chars);
        return implode(array_slice($chars, 0, 4));
    }

    public function getAccessToken(): string
    {
        return $this->access_token;
    }

    public function deactivate(): void
    {
        $this->status = self::USER_DEACTIVATED;
        $this->save();
    }
}
