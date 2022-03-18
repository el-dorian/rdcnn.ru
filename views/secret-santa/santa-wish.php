<?php

use app\assets\SnowAsset;
use app\models\database\Wish;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;
use yii\web\View;


/* @var $this View */
/* @var $model Wish */
SnowAsset::register($this);
?>

<div class="site-login text-center">

    <div id="ourSmallLogo" class="visible-sm visible-md visible-lg visible-xs margin"></div>
    <h2 class="text-center">Тут вы можете совершенно анонимно загадать желание. Любое.</h2>
    <h3>Желания такие же анонимные, как и Санты :) Никто не узнает, кто что загадал, но вдруг оно сбудется.</h3>
    <h3>Можно считать, что это аналог желания на бумажке, которую сжигают за новогодним столом. Только в отличие от
        желания на бумажке, возможно, кто-то увидит ваше <a href="/santa">тут</a></h3>

    <?php $form = ActiveForm::begin([
        'id' => 'wish-form',
        'layout' => 'horizontal',
        'fieldConfig' => [
            'labelOptions' => ['class' => 'control-label'],
        ],
    ]); ?>

    <?= $form->field($model, 'wish', ['template' => "<div class='col-xs-12 col-lg-offset-4 col-lg-4'>{label}</div><div class='col-xs-offset-3 col-xs-6 col-lg-offset-4 col-lg-4'>{input} </div><div class='col-xs-1'></div><div class='col-xs-12'>{error}</div>",])->textInput(['autofocus' => true,]) ?>

    <div class="form-group">
        <div class="col-sm-12 text-center">
            <?= Html::submitButton('Загадать', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
        </div>
    </div>
    <div>
        <?php ActiveForm::end(); ?>
    </div>
