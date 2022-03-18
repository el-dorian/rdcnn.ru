<?php


namespace app\controllers;


use app\models\AdministratorActions;
use app\models\database\PatientInfo;
use app\models\FileUtils;
use app\models\Rate;
use app\models\Telegram;
use app\models\User;
use JetBrains\PhpStorm\ArrayShape;
use Throwable;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Cookie;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UserController extends Controller
{
    #[ArrayShape(['access' => "array"])] public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'denyCallback' => function () {
                    return $this->redirect('/error', 404);
                },
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'delete-execution',
                            'rate-link-clicked',
                            'rate',
                            'review',
                        ],
                        'roles' => ['reader'],
                    ],

                    [
                        'allow' => true,
                        'actions' => [
                            'enter',
                            'unsubscribe',
                            'show-schedule-hash',
                        ],
                        'roles' => ['?', '@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws Throwable
     */
    public function actionDeleteExecution(): Response
    {
        if (Yii::$app->request->isPost) {
            // если удаление выполняется от лица администратора-удалю запись и перенаправлю на страницу администрирования
            if (Yii::$app->user->can('manage')) {
                $referer = explode('/', $_SERVER['HTTP_REFERER']);
                $executionNumber = $referer[array_key_last($referer)];
                AdministratorActions::simpleDeleteItem(User::findByUsername($executionNumber), false);
                return $this->redirect('/site/administrate', 301);
            }
            if (Yii::$app->user->can('read')) {
                // разлогиню пользователя и удалю учётную запись
                /** @var User $user */
                $user = Yii::$app->user->identity;
                $user->deactivate();
                Yii::$app->user->logout(true);
            }
        }
        throw new NotFoundHttpException();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionRateLinkClicked(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (Yii::$app->request->isPost) {
            if (Yii::$app->user->can('manage')) {
                $referer = explode('/', $_SERVER['HTTP_REFERER']);
                $id = $referer[array_key_last($referer)];
            } else if (Yii::$app->user->can('read')) {
                // разлогиню пользователя и удалю учётную запись
                $id = Yii::$app->user->identity->username;

            } else {
                throw new NotFoundHttpException("no");
            }
            $type = 'undefined';
            if (!empty(Yii::$app->request->post('type'))) {
                $type = Yii::$app->request->post('type');
            }
            Telegram::sendDebug("$id решил оставить отзыв на $type");

            $cookies = Yii::$app->response->cookies;

// добавление новой куки в HTTP-ответ
            $cookies->add(new Cookie([
                'name' => 'rated',
                'value' => 'true',
            ]));
            return ['status' => 'success'];
        }
        throw new NotFoundHttpException();
    }

    /**
     * @return string[]
     * @throws NotFoundHttpException
     */
    #[ArrayShape(['status' => "string"])] public function actionRate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (Yii::$app->request->isPost) {
            if (Yii::$app->user->can('manage')) {
                $referer = explode('/', $_SERVER['HTTP_REFERER']);
                $id = $referer[array_key_last($referer)];
            } else if (Yii::$app->user->can('read')) {
                $id = Yii::$app->user->identity->username;

            } else {
                throw new NotFoundHttpException("no");
            }
            if (!empty(Yii::$app->request->post('rate'))) {
                $rate = Yii::$app->request->post('rate');
                Rate::handleRate($id, $rate);
            }
            $cookies = Yii::$app->response->cookies;

// добавление новой куки в HTTP-ответ
            $cookies->add(new Cookie([
                'name' => 'rate_received',
                'value' => 'true',
            ]));
            return ['status' => 'success'];
        }
        throw new NotFoundHttpException();
    }

    /**
     * @return string[]
     * @throws NotFoundHttpException
     */
    #[ArrayShape(['status' => "string"])] public function actionReview(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (Yii::$app->request->isPost) {
            if (Yii::$app->user->can('manage')) {
                $referer = explode('/', $_SERVER['HTTP_REFERER']);
                $id = $referer[array_key_last($referer)];
            } else if (Yii::$app->user->can('read')) {
                $id = Yii::$app->user->identity->username;

            } else {
                throw new NotFoundHttpException("no");
            }
            Rate::handleReview($id);
            // получение коллекции (yii\web\CookieCollection) из компонента "response"
            $cookies = Yii::$app->response->cookies;

// добавление новой куки в HTTP-ответ
            $cookies->add(new Cookie([
                'name' => 'reviewed',
                'value' => 'true',
            ]));
            return ['status' => 'success'];
        }
        throw new NotFoundHttpException();
    }

    /**
     * @param $accessToken
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionEnter($accessToken): Response
    {
        // получу данные о пациенте и если он существует-залогиню его и перенаправлю в ЛК
        if (!empty($accessToken)) {
            $user = User::findIdentityByAccessToken($accessToken);
            if ($user !== null) {
                Yii::$app->user->login($user);
                return $this->redirect('/person/' . $user->username, 301);
            }
            Telegram::sendDebug("Попытка входа с токеном " . $accessToken);
        }
        throw new NotFoundHttpException();
    }

    public function actionUnsubscribe($token): void
    {
        $user = PatientInfo::findOne(['unsubscribe_token' => $token]);
        if ($user !== null) {
            $user->unsubscribed = 1;
            $user->save();
        }
        echo '<h2>Мы всё поняли и больше не будем вас беспокоить :)</h2>';
    }

    public function actionShowScheduleHash(): void
    {
        FileUtils::showScheduleHash();
    }
}