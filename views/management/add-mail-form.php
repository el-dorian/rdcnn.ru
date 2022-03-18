<?php

use app\models\database\Emails;
use app\models\Table_availability;
use app\models\User;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;


/* @var $this View */
/* @var $model Emails */

$patientInfo = User::findIdentity($model->patient_id);

if($patientInfo !== null){
    echo "<h2 class='text-center'>Номер обследования: {$patientInfo->username}</h2>";
    $name = Table_availability::getPatientName($patientInfo->username);
    if($name !== null){
        echo "<h3 class='text-center'>{$name}</h3>";
    }
}

$form = ActiveForm::begin(['id' => 'addMailForm', 'options' => ['class' => 'form-horizontal bg-default', 'enctype' => 'multipart/form-data'], 'enableAjaxValidation' => false, 'validateOnSubmit'  => false, 'action' => ['/mail/add']]);

echo $form->field($model, 'patient_id', ['template' => '{input}'])->hiddenInput()->label(false);
    echo $form->field($model, 'address', ['template' =>
        '<div class="col-sm-5">{label}</div><div class="col-sm-7">{input}{error}{hint}</div>','inputOptions' =>
        ['autofocus' => 'autofocus', 'class' => 'form-control', 'tabindex' => '1']])
        ->textInput(['autocomplete' => 'off', 'focus' => true, 'type' => 'text'])
        ->hint('Адрес электронной почты (можно ввести несколько, разделители: пробел, запятая, точка с запятой)')
        ->label('Email');

echo Html::submitButton('Сохранить', ['class' => 'btn btn-success   ', 'id' => 'addSubmit', 'data-toggle' => 'tooltip', 'data-placement' => 'top', 'data-html' => 'true',]);
ActiveForm::end();
?>
<script><?=file_get_contents(Yii::getAlias('@webroot') . '/js/addMail.js')?></script>
