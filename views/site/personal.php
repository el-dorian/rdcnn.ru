<?php

use app\assets\PersonalAsset;
use app\models\database\Archive_execution;
use app\models\database\Reviews;
use app\models\database\Archive_complex_execution_info;
use app\models\database\TempDownloadLinks;
use app\models\ExecutionHandler;
use app\models\Table_availability;
use app\models\User;
use app\priv\Info;
use chillerlan\QRCode\QRCode;
use nirvana\showloading\ShowLoadingAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;


PersonalAsset::register($this);
ShowLoadingAsset::register($this);

/* @var $this View */
/* @var $execution User */


$this->title = 'РДЦ, обследование ' . $execution->username;

?>

    <div id="ourLogo" class="visible-sm visible-md visible-lg "></div>
    <div id="ourSmallLogo" class="visible-xs"></div>

    <h1 class="text-center">Обследование № <?= $execution->username ?></h1>

<?php
$name = Table_availability::getPatientName($execution->username);
if ($name !== null) {
    echo "<h2 class='text-center'>$name</h2>";
}
?>

    <div class="col-sm-12 col-md-6 col-md-offset-3">

        <?php
        echo "<div id='availabilityTimeContainer' class='alert alert-info text-center " . (ExecutionHandler::isConclusion($execution->username) ? '' : 'hidden') . "'><span class='glyphicon glyphicon-info-sign'></span> Данные обследования будут доступны в течение<br/> <span id='availabilityTime'></span></div>";
        ?>
    </div>

    <div class="col-sm-12 col-md-6 col-md-offset-3">
        <div id="conclusionsContainer">
            <?php
            // получу список заключений
            $conclusions = Table_availability::getConclusions($execution->username);
            if (empty($conclusions)) {
                echo "<a id='conclusionNotReadyBtn' class='btn btn-primary btn-block margin with-wrap disabled' role='button'>Заключение врача в работе</a>";
            } else {
                foreach ($conclusions as $conclusion) {
                    echo "
                <a href='" . Url::toRoute(['/download/conclusion', 'href' => $conclusion->file_name]) . "' class='btn btn-primary btn-block margin with-wrap conclusion hinted' data-href='$conclusion->file_name'>Загрузить заключение врача<br/>$conclusion->execution_area</a>
                <a target='_blank' href='" . Url::toRoute(['/download/print-conclusion', 'href' => $conclusion->file_name]) . "' class='btn btn-info btn-block margin with-wrap print-conclusion hinted' data-href='$conclusion->file_name'>Распечатать заключение врача<br/>$conclusion->execution_area</a>
";
                }
            }
            ?>
        </div>
        <div id="executionContainer">
            <?php
            // если доступно заключение- дам ссылку на него
            if (ExecutionHandler::isExecution($execution->username)) {
                // проверю, создана ли внешняя ссылка на обследование. Если она есть- добавлю кнопку "скопировать ссылку" и "удалить сылку".
                // Если нет- добавлю кнопку создания ссылки
                if (TempDownloadLinks::executionLinkExists($execution->username)) {
                    $downloadLinkText = '';
                } else {
                    $downloadLinkText = '<button class="btn btn-default"><span class="text-success">Создать ссылку общего доступа</span></button>';
                }
                echo "<a id='executionReadyBtn' href='" . Url::toRoute('/download/execution') . "' class='btn btn-primary  btn btn-block margin with-wrap hinted' data-href='/download/execution'>Загрузить архив обследования</a><br/><a target='_blank' href='https://www.youtube.com/watch?v=FW4MCyQQoO4&feature=share' class='btn btn-default btn-block margin with-wrap'>Как просмотреть архив обследования(видео)</a><a target='_blank' href='/images/ИНСТРУКЦИЯ.pdf' class='btn btn-default btn-block margin with-wrap' role='button'>Как просмотреть архив обследования</a>";
            } else {
                echo "<a id='executionNotReadyBtn' class='btn btn-primary btn-block margin with-wrap disabled' role='button'>Архив обследования подготавливается</a>";
            }
            ?>
        </div>
        <?php

        echo "<a id='clearDataBtn' class='btn btn-danger btn-block margin with-wrap' role='button'><span class='glyphicon glyphicon-trash'></span> Удалить данные</a>";
        echo Html::beginForm(['/site/logout'])
            . Html::submitButton(
                '<span class="glyphicon glyphicon-log-out"></span> Выйти из учётной записи',
                ['class' => 'btn btn-primary btn btn-block margin with-wrap logout']
            )
            . Html::endForm();
        ?>
    </div>

    <div class="col-sm-12 col-md-6 col-md-offset-3 text-center margin">
        <div class="alert alert-success"><span class='glyphicon glyphicon-info-sign'></span> Если Вам необходима печать
            на
            заключение, обратитесь в центр, где Вы проходили
            исследование
        </div>
        <div id="rateBlock" class="text-center">
            <?php
            $cookies = Yii::$app->request->cookies;
            if (!$cookies->has("rate_received") || Reviews::haveNoRate($execution->username)) {
                ?>
                <div id="rateList">
                    <span class="glyphicon glyphicon-star-empty star" data-rate="1"></span>
                    <span class="glyphicon glyphicon-star-empty star" data-rate="2"></span>
                    <span class="glyphicon glyphicon-star-empty star" data-rate="3"></span>
                    <span class="glyphicon glyphicon-star-empty star" data-rate="4"></span>
                    <span class="glyphicon glyphicon-star-empty star" data-rate="5"></span>
                </div>
                <?php
            }
            if (!$cookies->has("reviewed") || Reviews::haveNoReview($execution->username)) {
                ?>
                <form id="reviewForm">
                    <div class="form-group">
                        <label for="review">Ваш отзыв</label>
                        <textarea name="reviewArea" class="form-control" id="review" rows="3"></textarea>
                    </div>
                    <button class="btn btn-success">Отправить</button>
                </form>
                <?php
            }
            ?>
        </div>
        <?php
        echo "<div id='removeReasonContainer' class='alert alert-info " . (ExecutionHandler::isConclusion($execution->username) ? '' : 'hidden') . "'><span class='glyphicon glyphicon-info-sign'></span> Ограничение доступа к данным исследования по времени необходимо в целях обеспечения безопасности Ваших персональных данных</div>";
        ?>

        <a href="tel:+78312020200" class="btn btn-default margin"><span
                    class="glyphicon glyphicon-earphone text-success"></span><span
                    class="text-success"> +7(831)20-20-200</span></a><br/>
        <a target="_blank" href="https://мрт-кт.рф" class="btn btn-default"><span
                    class="glyphicon glyphicon-globe text-success"></span><span
                    class="text-success"> мрт-кт.рф</span></a>
    </div>

    <div class="col-sm-12 text-center">

        <a class="btn btn-primary" role="button" data-toggle="collapse" href="#collapseExample" aria-expanded="false"
           aria-controls="collapseExample">
            Нажмите, чтобы увидеть актуальные предложения
        </a>
        <div class="collapse" id="collapseExample">
            <div class="well">
                <a target="_blank" href="https://xn----ttbeqkc.xn--p1ai/nn/actions">
                    <img class="advice" alt="advice image" src="https://xn----ttbeqkc.xn--p1ai/actions.png"/>
                </a>
            </div>
        </div>
    </div>
<?php

if (Yii::$app->user->can("manage")) {
    $data = 'https://rdcnn.ru/enter/' . $execution->access_token;

// quick and simple:
    echo '<div class="text-center"><img src="' . (new QRCode)->render($data) . '" alt="QR Code" /></div>';

    // show previous executions list
    $previousExecutions = Archive_execution::getPreviousForUser($execution);
    if (!empty($previousExecutions)) {
        echo "<table class='table table-condensed'><thead><tr><th>Вид</th><th>Номер обследования</th><th>Дата</th><th>Зона</th><th>Действие</th></tr></thead><tbody>";
        foreach ($previousExecutions as $execution) {
            // check existing pdf conclusion file
            $fullInfo = Archive_complex_execution_info::findOne(['execution_identifier' => $execution->id]);
            if (!empty($execution->pdfPath)) {
                $pdfPath = Info::PDF_ARCHIVE_PATH . $execution->pdfPath;
                if ($fullInfo !== null && is_file($pdfPath)) {
                    echo "<tr><td>МРТ</td><td>$fullInfo->execution_number</td><td>$fullInfo->execution_date</td><td>$fullInfo->execution_area</td><td><a href='/archive-dl/$execution->id' target='_blank'>Скачать</a></td></tr>";
                }
            }
        }
        echo "</tbody></table>";
    } else {
        echo "<div class='col-sm-4 offset-4'>Предыдущих обследований не найдено</div>";
    }
}



