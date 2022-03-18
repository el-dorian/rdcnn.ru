<?php /** @noinspection PhpUndefinedClassInspection */

namespace app\controllers;

use app\models\AdministratorActions;
use app\models\ExecutionHandler;
use app\models\FileUtils;
use app\models\LoginForm;
use app\models\Telegram;
use app\models\User;
use app\models\Utils;
use app\models\utils\GrammarHandler;
use app\models\utils\Management;
use app\models\utils\PatientSearch;
use app\priv\Info;
use JetBrains\PhpStorm\ArrayShape;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SiteController extends Controller
{
    /**
     * @inheritdoc
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        if ($action->id === 'test') {
            // отключу csrf для возможности запроса
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

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
                            'index',
                            'test',
                            'privacy-policy',
                            'error',
                            'dicom-viewer'
                        ],
                        'roles' => ['?', '@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['check'],
                        'roles' => ['?', '@'],
                        'ips' => Info::ACCEPTED_IPS,
                    ],
                    [
                        'allow' => true,
                        'actions' => ['iolj10zj1dj4sgaj45ijtse96y8wnnkubdyp5i3fg66bqhd5c8'],
                        'roles' => ['?', '@'],
                        'ips' => Info::ACCEPTED_IPS,
                    ],

                    [
                        'allow' => true,
                        'actions' => [
                            'logout',
                            'availability-check'
                        ],
                        'roles' => ['@'],
                    ],

                    [
                        'allow' => true,
                        'actions' => [
                            'management',
                            'patient-search',
                            'test'
                        ],
                        'roles' => [
                            'manager'
                        ],
                        //'ips' => Info::ACCEPTED_IPS,
                    ],
                ],
            ],
        ];
    }

    #[ArrayShape(['error' => "string[]"])] public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }


    /**
     * Displays homepage.
     *
     * @param null $executionNumber
     * @return array|Response|string
     * @throws Exception
     */
    public function actionIndex($executionNumber = null): array|Response|string
    {
        Management::handleChanges();
        // если пользователь не залогинен-показываю ему страницу с предложением ввести номер обследования и пароль
        if (Yii::$app->user->isGuest) {
            if (Yii::$app->request->isGet) {
                $model = new LoginForm(['scenario' => LoginForm::SCENARIO_USER_LOGIN]);
                if ($executionNumber !== null) {
                    $model->username = GrammarHandler::toLatin($executionNumber);
                }
                return $this->render('login', ['model' => $model]);
            }
            if (Yii::$app->request->isPost && Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                // попробую залогинить
                $model = new LoginForm(['scenario' => LoginForm::SCENARIO_USER_LOGIN]);
                $model->load(Yii::$app->request->post());
                if ($model->loginUser()) {
                    // загружаю личный кабинет пользователя
                    return ['status' => 1];
                }
                return ['status' => 0, 'errors' => $model->errors];
            }
            if (Yii::$app->request->isPost) {
                // попробую залогинить
                $model = new LoginForm(['scenario' => LoginForm::SCENARIO_USER_LOGIN]);
                $model->load(Yii::$app->request->post());
                if ($model->loginUser()) {
                    // загружаю личный кабинет пользователя
                    return $this->redirect('/person/' . Yii::$app->user->identity->username, 301);
                }
                return $this->render('login', ['model' => $model]);
            }
        }
        // если пользователь залогинен как администратор-показываю ему страницу для скачивания
        if (Yii::$app->user->can('manage')) {
            if ($executionNumber !== null) {
                // получу информацию об обследовании
                $execution = User::findByUsername($executionNumber);
                if ($execution !== null) {
                    return $this->render('personal', ['execution' => $execution]);
                }
            }

// страница не найдена, перенаправлю на страницу менеджмента
            return $this->redirect(Url::toRoute('site/iolj10zj1dj4sgaj45ijtse96y8wnnkubdyp5i3fg66bqhd5c8'));
        }
        if (Yii::$app->user->can('read')) {
            $execution = User::findByUsername(Yii::$app->user->identity->username);
            if ($execution !== null) {
                if (time() - Info::DATA_SAVING_TIME < $execution->created_at) {
                    return $this->render('personal', ['execution' => $execution]);
                }
                return $this->render('personal-expired', ['execution' => $execution]);
            }

            return $this->render('error', ['message' => 'Страница не найдена']);
        }
        return $this->render('error', ['message' => 'Страница не найдена']);
    }

    /**
     * @return string|Response
     * @throws \Exception
     */
    public function actionIolj10zj1dj4sgaj45ijtse96y8wnnkubdyp5i3fg66bqhd5c8(): Response|string
    {
        Management::handleChanges();
        // если пользователь не залогинен-показываю ему страницу с предложением ввести номер обследования и пароль
        if (Yii::$app->user->isGuest) {
            if (Yii::$app->request->isGet) {
                $model = new LoginForm(['scenario' => LoginForm::SCENARIO_ADMIN_LOGIN]);
                return $this->render('administrationLogin', ['model' => $model]);
            }
            if (Yii::$app->request->isPost) {
                // попробую залогинить
                $model = new LoginForm(['scenario' => LoginForm::SCENARIO_ADMIN_LOGIN]);
                $model->load(Yii::$app->request->post());
                if ($model->loginAdmin()) {
                    // загружаю страницу управления
                    return $this->redirect('iolj10zj1dj4sgaj45ijtse96y8wnnkubdyp5i3fg66bqhd5c8', 301);
                }
                return $this->render('administrationLogin', ['model' => $model]);
            }
            // зарегистрирую пользователя как администратора
            //LoginForm::autoLoginAdmin();
        }
        // если пользователь админ
        if (Yii::$app->user->can('manage')) {
            // очищу неиспользуемые данные
            //AdministratorActions::clearGarbage();
            $this->layout = 'administrate';
            if (Yii::$app->request->isPost) {
                // выбор центра, обследования которого нужно отображать
                AdministratorActions::selectCenter();
                AdministratorActions::selectTime();
                AdministratorActions::selectSort();
                return $this->redirect('site/iolj10zj1dj4sgaj45ijtse96y8wnnkubdyp5i3fg66bqhd5c8', 301);
            }
            // получу все зарегистрированные обследования
            $executionsList = User::findAllRegistered();
            // отсортирую список
            $executionsList = Utils::sortExecutions($executionsList);
//            foreach ($executionsList as $item) {
//                if(AuthAssignment::findOne(['user_id' => $item->id]) === null){
//                    (new AuthAssignment(['user_id' => $item->id, 'item_name' => 'reader', 'created_at' => time()]))->save();
//                }
//            }
            $model = new ExecutionHandler(['scenario' => ExecutionHandler::SCENARIO_ADD]);
            return $this->render('administration', ['executions' => $executionsList, 'model' => $model]);
        }

// редирект на главную
        return $this->redirect('site/index', 301);
    }

    /**
     */
    public function actionTest()
    {
        $dir = "C:\\test";
        $entities = array_slice(scandir($dir), 2);

        foreach ($entities as $entity) {
            $path = $dir . '\\' . $entity;
            if (is_dir($path)) {
                if (str_starts_with($path, $dir . '\\.')) {
                    Telegram::sendDebug("try to handle the root dir $path");
                    rename($path, str_replace('.', '', $path));
                    continue;
                }
            }
        }
        var_dump($entities);
    }


    /**
     * @throws NotFoundHttpException
     */
    public function actionError(): string
    {
        throw new NotFoundHttpException();
    }

    public function actionLogout(): Response
    {
        if (Yii::$app->request->isPost) {
            Yii::$app->user->logout();
            return $this->redirect('/', 301);
        }
        return $this->redirect('/', 301);
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function actionAvailabilityCheck(): array
    {
        try {
            Management::handleChanges();
        } catch (\Exception) {

        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ExecutionHandler::checkAvailability();
    }

    /**
     */
    public function actionCheck(): void
    {
        try {
            Management::handleChanges();
            ExecutionHandler::check();
        } catch (\Exception) {
        }
    }

    public function actionManagement(): string
    {
        $outputInfo = FileUtils::getOutputInfo();
        $errorsInfo = FileUtils::getErrorInfo();
        $updateOutputInfo = FileUtils::getUpdateOutputInfo();
        $updateErrorsInfo = FileUtils::getUpdateErrorInfo();
        $errors = FileUtils::getServiceErrorsInfo();
        return $this->render('management', ['outputInfo' => $outputInfo, 'errorsInfo' => $errorsInfo, 'errors' => $errors, 'updateOutputInfo' => $updateOutputInfo, 'updateErrorsInfo' => $updateErrorsInfo]);
    }

    public function actionDicomViewer(): string
    {
        $this->layout = 'empty';
        return $this->render('dicom-viewer');
    }

    public function actionPrivacyPolicy(): string
    {
        return $this->renderPartial('privacy-policy');
    }

    /**
     * @throws \Exception
     */
    public function actionPatientSearch(): string
    {
        $this->layout = 'administrate';
        $model = new PatientSearch();
        $results = null;
        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            if($model->validate()){
                $results = $model->search();
            }
        }
        return $this->render('patient-search', ['model' => $model, 'results' => $results]);
    }

}
