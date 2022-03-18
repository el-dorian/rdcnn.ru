<?php


namespace app\models;


use Yii;
use yii\base\Exception;
use yii\base\Model;

class Test extends Model
{
    /**
     * @throws Exception
     * @throws \Exception
     */
    public static function test(){

        // регистрация нового пользователя

/*        $password = Yii::$app->getSecurity()->generateRandomString(10);
        $hash = Yii::$app->getSecurity()->generatePasswordHash($password);
        $auth_key = Yii::$app->getSecurity()->generateRandomString(32);
        $newUser = new User;
        $newUser->username = 'adfascvlgalsegrlkuglkbaldasdf';
        $newUser->auth_key = $auth_key;
        $newUser->password_hash = $hash;
        $newUser->status = 1;
        if($newUser->save()){
            echo $password;
        }*/
        // добавление ролей
        // Добавление роли =================================================
/*        $auth = Yii::$app->authManager;
        // добавляем разрешение "readSite"
        $read = $auth->createPermission('read');
        $read->description = 'Возможность чтения';
        $auth->add($read);

        $transaction = new DbTransaction();

        // добавляем разрешение "manageSite"
        $manage = $auth->createPermission('manage');
        $manage->description = 'Возможность управления';
        $auth->add($manage);


        // добавляем роль "reader" и даём роли разрешение "write"
        $reader = $auth->createRole('reader');
        $reader->description = 'Учётная запись клиента';
        $auth->add($reader);
        $auth->addChild($reader, $read);
        // добавляем роль "manager" и даём роли разрешение "mamage"
        $manager = $auth->createRole('manager');
        $manager->description = 'Учётная запись администратора';
        $auth->add($manager);
        $auth->addChild($manager, $manage);
        $auth->addChild($manager, $reader);

        // Назначение ролей пользователям. 1 и 2 это IDs возвращаемые IdentityInterface::getId()
        // обычно реализуемый в модели User.
        $auth->assign($reader, 8);
        $auth->assign($manager, 8);
        $transaction->commitTransaction();*/
    }
}