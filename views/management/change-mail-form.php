<?php

use app\models\database\Emails;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;


/* @var $this View */
/* @var $model Emails */

$form = ActiveForm::begin(['id' => 'addMailForm', 'options' => ['class' => 'form-horizontal bg-default', 'enctype' => 'multipart/form-data'], 'enableAjaxValidation' => false, 'validateOnSubmit'  => false, 'action' => ['/mail/change']]);

echo $form->field($model, 'id', ['template' => '{input}'])->hiddenInput()->label(false);
echo $form->field($model, 'patient_id', ['template' => '{input}'])->hiddenInput()->label(false);
echo $form->field($model, 'address', ['template' =>
    '<div class="col-sm-5">{label}</div><div class="col-sm-7">{input}{error}{hint}</div>','inputOptions' =>
    ['autofocus' => 'autofocus', 'class' => 'form-control', 'tabindex' => '1']])
    ->textInput(['autocomplete' => 'off', 'focus' => true, 'type' => 'text'])
    ->hint('Адрес электронной почты (можно ввести несколько, разделители: пробел, запятая, точка с запятой)')
    ->label('Email');

echo Html::submitButton('Сохранить изменения', ['class' => 'btn btn-success   ', 'id' => 'addSubmit', 'data-toggle' => 'tooltip', 'data-placement' => 'top', 'data-html' => 'true',]);
ActiveForm::end();

echo '<div class="text-center"><button class="btn btn-default" id="deleteMailBtn" data-id="' . $model->patient_id . '"><span class="text-danger">Удалить почту</span></button></div>';
?>
<script><?=file_get_contents(Yii::getAlias('@webroot') . '/js/changeMail.js')?></script>
