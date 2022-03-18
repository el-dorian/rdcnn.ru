<?php

/* @var $this View */

/* @var $execution User */


use app\models\Table_availability;
use app\models\User;
use yii\helpers\Html;
use yii\web\View;

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
<div class="col-sm-12 col-md-6 col-md-offset-3 text-center margin">
    <h4>Закончилось время хранения данных на сервере</h4>
    <p>Данные хранятся на сервере в течение 5 дней после прохождения обследования. Чтобы вновь получить доступ к данным- обратитесь к нам. </p>
</div>


<div class="col-sm-12 col-md-6 col-md-offset-3">
    <?php
    echo Html::beginForm(['/site/logout'])
        . Html::submitButton(
            '<span class="glyphicon glyphicon-log-out"></span> Выйти из учётной записи',
            ['class' => 'btn btn-primary btn btn-block margin with-wrap logout']
        )
        . Html::endForm();
    ?>
</div>

<div class="col-sm-12 col-md-6 col-md-offset-3 text-center margin">
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