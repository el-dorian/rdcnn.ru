<?php


namespace app\controllers;


use app\models\Telegram;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

class TelegramController extends Controller
{
    /**
     * @inheritdoc
     * @throws BadRequestHttpException
     */
    public function beforeAction($action):bool
    {
        if ($action->id === 'connect') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }
    public function actionConnect(): void
    {
        // обработаю запрос
        Telegram::handleRequest();
    }
}