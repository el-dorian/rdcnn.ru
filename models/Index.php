<?php
/**
 * Created by PhpStorm.
 * User: eldor
 * Date: 15.02.2019
 * Time: 21:03
 */

namespace app\models;


use Yii;
use yii\base\Model;

class Index extends Model
{

    public function showIdentity()
    {
    }

    public function createPermissions(){
/*
        try{

            // Добавление роли =================================================
            $auth = Yii::$app->authManager;

            // добавляем разрешение "readSite"
            $read = $auth->createPermission('read');
            $read->description = 'Возможность чтения';
            $auth->add($read);

            // добавляем роль "reader" и даём роли разрешение "read"
            $reader = $auth->createRole('reader');
            $reader->description = 'Учётная запись читателя';
            $auth->add($reader);
            $auth->addChild($reader, $read);

            // Назначение ролей пользователям. 1 и 2 это IDs возвращаемые IdentityInterface::getId()
            // обычно реализуемый в модели User.
            $auth->assign($reader, 1);
        }
        catch (\Exception $e){
            die('error!');
        }*/
    }
}