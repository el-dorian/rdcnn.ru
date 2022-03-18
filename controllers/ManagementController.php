<?php


namespace app\controllers;


use app\models\database\Emails;
use app\models\ExecutionHandler;
use app\models\FileUtils;
use app\models\Table_blacklist;
use app\models\Telegram;
use app\models\User;
use app\models\utils\ComHandler;
use app\models\utils\FirebaseHandler;
use app\models\utils\MailHandler;
use app\models\utils\MailSettings;
use app\models\utils\Management;
use app\priv\Info;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Throwable;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ManagementController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'denyCallback' => function () {
                    return $this->redirect('error', 404);
                },
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => [
                            'check-update',
                            'check-changes',
                            'update-dependencies',
                            'restart-server',
                            'send-mail',
                            'add-backgrounds',
                            'clear-blacklist-table',
                            'handle-mail',
                            'add-mail',
                            'change-mail',
                            'delete-mail',
                            'delete-conclusions',
                            'send-message',
                            'archive',
                            'send-firebase-test',
                            'send-firebase-topic',
                        ],
                        'roles' => [
                            'manager'
                        ],
                        'ips' => Info::ACCEPTED_IPS,
                    ],
                ],
            ],
        ];
    }

    /**
     * <b>принудительная загрузка обновления с гитхаба</b>
     */
    public function actionCheckUpdate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Management::updateSoft();
        return ['status' => 1, 'message' => 'Запущено обновление ПО'];
    }

    /**
     * <b>обновление зависимостей Composer</b>
     * @return array
     */
    #[ArrayShape(['status' => "int", 'message' => "string"])] public function actionUpdateDependencies(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $file = Yii::$app->basePath . '\\composerUpdate.bat';
        Management::startScript($file);
        return ['status' => 1, 'message' => 'Запущено обновление зависимостей'];
    }

    public function actionResetChangeCheckCounter(): void
    {
        FileUtils::setUpdateFinished();
    }

    public function actionCheckChanges(): void
    {
        try{ExecutionHandler::check();}
        catch (Exception $e){
            echo "error: " . $e->getMessage();
        }
    }

    #[ArrayShape(['status' => "int", 'message' => "string"])] public function actionRestartServer(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $file = Yii::$app->basePath . '\\restartServer.bat';
        ComHandler::runCommand($file);
        return ['status' => 1, 'message' => 'Инициирована перезагрузка сервера'];

    }

    public function actionCheckJava(): void
    {
        $file = Yii::$app->basePath . '\\checkJava.bat';
        if (is_file($file)) {
            $command = $file . ' ' . Yii::$app->basePath;
            $outFilePath = Yii::$app->basePath . '\\logs\\java_info.log';
            $outErrPath = Yii::$app->basePath . '\\logs\\java_info_error.log';
            $command .= ' > ' . $outFilePath . ' 2>' . $outErrPath . ' &"';
            echo $command;
            try {
                // попробую вызвать процесс асинхронно
                /** @noinspection PhpUndefinedClassInspection */
                /** @noinspection PhpFullyQualifiedNameUsageInspection */
                $handle = new \COM('WScript.Shell');
                /** @noinspection PhpUndefinedMethodInspection */
                $handle->Run($command, 0, false);
            } catch (Exception $e) {
                exec($command);
            }
        }
    }

    public function actionSendMail(): array
    {
        try {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $settingsFile = Yii::$app->basePath . '\\priv\\mail_settings.conf';
            // получу данные
            $content = file_get_contents($settingsFile);
            $settingsArray = mb_split("\n", $content);
            if (count($settingsArray) === 3) {
                // получу текст письма
                $text = 'test';
                // отправлю письмо
                $mail = Yii::$app->mailer->compose()
                    ->setFrom([MailSettings::getInstance()->address => 'Ошибки сервера РДЦ'])
                    ->setSubject('Найдены новые ошибки')
                    ->setHtmlBody($text)
                    ->setTo(['eldorianwin@gmail.com' => 'eldorianwin@gmail.com']);
                // попробую отправить письмо, в случае ошибки- вызову исключение
                $mail->send();
                return ['status' => 1, 'message' => 'Тестовое письмо успешно отправлено'];
            }
            return ['status' => 1, 'message' => 'Отсутствуют настройки почты'];
        } catch (Exception $e) {
            return ['status' => 1, 'message' => 'Ошибка отправки почты: ' . $e->getMessage()];
        }
    }

    public function actionHandleMail($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // получу пациента с данным id. Если адрес почты ещё не назначен- отправлю форму для назначения адреса
        $patient = User::findIdentity($id);
        if ($patient !== null) {
            $existentMail = Emails::findOne(['patient_id' => $patient->id]);
            if ($existentMail === null) {
                $model = new Emails();
                $model->patient_id = $patient->id;
                return ['status' => 1, 'header' => 'Добавление адреса электронной почты', 'view' => $this->renderAjax('add-mail-form', ['model' => $model])];
            }
            // предложу заменить адрес или удалить почту
            return ['status' => 1, 'header' => 'Изменение адреса электронной почты', 'view' => $this->renderAjax('change-mail-form', ['model' => $existentMail])];
        }
        return null;
    }

    public function actionAddMail()
    {
        // добавлю адрес электронной почты, если он валидный и ещё не зарегистрирован
        Yii::$app->response->format = Response::FORMAT_JSON;
        $form = new Emails();
        $form->load(Yii::$app->request->post());
        // проверю, что данному обследованию ещё не назначен адрес
        if (!Emails::checkExistent($form->patient_id) && $form->save()) {
            return ['status' => 1];
        }
        return ['status' => 2, 'message' => 'Не удалось сохранить адрес'];
    }

    public function actionChangeMail()
    {
        // добавлю адрес электронной почты, если он валидный и ещё не зарегистрирован
        Yii::$app->response->format = Response::FORMAT_JSON;
        $form = Emails::findOne(Yii::$app->request->post()['Emails']['id']);
        if ($form !== null) {
            $form->load(Yii::$app->request->post());
            // проверю, что данному обследованию ещё не назначен адрес
            if ($form->save()) {
                return ['status' => 1];
            }
        }
        return ['status' => 2, 'message' => 'Не удалось сохранить адрес'];
    }

    public function actionDeleteMail()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $existentMail = Emails::findOne(['patient_id' => Yii::$app->request->post()['id']]);
        if ($existentMail !== null) {
            try {
                $existentMail->delete();
                return ['status' => 1];
            } catch (Throwable $e) {
            }
        }
        return ['status' => 2, 'message' => 'Не удалось удалить адрес электронной почты'];
    }

    public function actionDeleteConclusions($executionNumber): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // удалю все заключения по данному обследованию
        ExecutionHandler::deleteAllConclusions($executionNumber);
        return ['status' => 1, 'message' => 'Заключения удалены'];
    }

    public function actionClearBlacklistTable(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // удалю все записи из чёрного списка
        Table_blacklist::clear();
        return ['status' => 1, 'message' => 'Чёрный список вычищен', 'reload' => true];
    }

    public function actionSendMessage(): array
    {
        if (Yii::$app->request->isAjax && Yii::$app->request->isPost) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return MailHandler::sendMailing();
        }
        throw new NotFoundHttpException();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionSendFirebaseTest(): array
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return FirebaseHandler::sendTest();
        }
        throw new NotFoundHttpException();
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionSendFirebaseTopic(): array
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return FirebaseHandler::sendTopicTest();
        }
        throw new NotFoundHttpException();
    }
    /**
     */
    public function actionArchive(): void
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $conclusionFile = 'C:\pdf\1.pdf';
            $file = Yii::$app->basePath . '\\yii.bat';
            if (is_file($file)) {
                $command = "$file console/archive $conclusionFile";
                file_put_contents(Yii::$app->basePath . "/logs/archive.log", time() . " $command\n", FILE_APPEND);
                exec($command);
            }
        }
    }
}