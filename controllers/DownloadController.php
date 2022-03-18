<?php


namespace app\controllers;


use app\models\utils\DownloadHandler;
use app\models\utils\FilesHandler;
use app\models\Viber;
use yii\filters\AccessControl;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class DownloadController extends Controller
{
    public function behaviors(): array
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
                            'execution',
                            'conclusion',
                            'print-conclusion',
                            'download-from-archive'
                        ],
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'actions' => [
                            'download-temp',
                            'download-once'],
                        'roles' => ['@', '?'],
                    ],
                    [
                        'allow' => true,
                        'actions' => [
                            'drop',
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

    /**
     * @inheritdoc
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        if ($action->id === 'download-temp' || $action->id === 'drop' || $action->id === 'download-once') {
            // отключу csrf для возможности запроса
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Скачивание заключения
     * @throws NotFoundHttpException
     */
    public function actionExecution(): void
    {
        DownloadHandler::handleExecution();
    }

    /**
     * Скачивание заключения
     * @param $href string <p>Имя файла в виде 1.pdf</p>
     * @throws NotFoundHttpException <p>В случае отсутствия прав доступа или файла- ошибка</p>
     */
    public function actionConclusion(string $href): void
    {
        DownloadHandler::handleConclusion($href);
    }

    /**
     * Распечатывание заключения
     * @param $href
     * @throws NotFoundHttpException
     */
    public function actionPrintConclusion($href): void
    {
        DownloadHandler::handleConclusion($href, true);
    }

    /**
     * @param $link
     * @throws NotFoundHttpException
     */
    public function actionDownloadTemp($link): void
    {
        Viber::downloadTempFile($link);
    }
    /**
     * @param $link
     * @throws NotFoundHttpException
     */
    public function actionDownloadOnce($link): void
    {
        Viber::downloadOnceFile($link);
    }

    public function actionDrop(): void
    {
        FilesHandler::handleDroppedFile(UploadedFile::getInstanceByName('file'));
    }

    public function actionDownloadFromArchive($id){
        DownloadHandler::downloadArchiveConclusion($id);
    }
}