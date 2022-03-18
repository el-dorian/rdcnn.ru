<?php

namespace app\controllers;

use app\models\database\Santa;
use app\models\database\Wish;
use app\models\utils\MailHandler;
use JetBrains\PhpStorm\ArrayShape;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

class SecretSantaController extends Controller
{
    /**
     * access control
     */
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
                            'registration',
                            'wish',
                            'sent',
                            'not-received',
                        ],
                        'roles' => ['?', '@'],
                    ],
                ],
            ],
        ];
    }

    public function actionRegistration()
    {
        $this->layout = '@app/views/layouts/snowed';
        $model = new Santa(['scenario' => Santa::SCENARIO_REGISTER]);
        return $this->render('santa-registration', ['model' => $model]);
//        $model = new Santa(['scenario' => Santa::SCENARIO_REGISTER]);
//        $model->load(Yii::$app->request->post());
//        $model->email = trim($model->email);
//        $model->email = mb_strtolower($model->email);
//        if ($model->validate() && $model->register()) {
//            return $this->render('you-are-santa-now');
//        }
//        return $this->render('santa-registration', ['model' => $model]);
    }

    public function actionWish()
    {
        $this->layout = '@app/views/layouts/snowed';
        if (Yii::$app->request->isGet) {
            $model = new Wish(['scenario' => Wish::SCENARIO_REGISTER]);
            return $this->render('santa-wish', ['model' => $model]);
        }
        $model = new Wish(['scenario' => Wish::SCENARIO_REGISTER]);
        $model->load(Yii::$app->request->post());
        if ($model->validate() && $model->register()) {
            Yii::$app->session->setFlash("success", "Ага, получили желание. Ничего не можем обещать, но вдруг сбудется ;) Счастливого нового года!");
        }
        return $this->redirect("/secret-santa/wish");
    }

    public function actionSent($key): Response|string
    {
        $user = Santa::findOne(['key' => $key]);
        if ($user !== null) {
            $user->setScenario(Santa::SCENARIO_PREMAILED);
            $user->mark_as_send = 1;
            $user->save();
            return $this->render('santa-send-accepted');
        }
        return $this->redirect('/error', 404);
    }

    public function actionNotReceived($key)
    {
        $user = Santa::findOne(['key' => $key]);
        if ($user !== null) {
            // отправлю письмо с уведомлением тому, кому должен был отправить подарок
            $giver = Santa::findOne(['baby' => $user->id]);
            if ($giver !== null) {
                $receiverName = ucwords($user->name);
                MailHandler::sendMessage('Привет, Санта', <<<EOF
Привет. Это письмо отправлено потому, что тот, кому вы должны подарить подарок, его не получил.
Если вы ещё не успели его отправить- поторопитесь, праздники уже закончились.
Если отправили- пожалуйста, подтвердите это в письме, которое получили чуть раньше, может быть, он где-то затерялся.
На всякий случай, вот данные того, кому нужно было отправить подарок.
<h2>Идентификатор получателя: $user->id</h2>
<h2>ФИО получателя: $receiverName</h2>
Спасибо за участие :)
EOF
                    ,
                    $giver->email,
                    $giver->name,
                    null, false, true);
                return $this->render('santa-notified');
            }
        }
        return $this->redirect('/error', 404);
    }
}