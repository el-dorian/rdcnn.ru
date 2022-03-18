<?php

use app\models\ExecutionHandler;
use kartik\file\FileInput;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;


/* @var $this View */
/* @var $model ExecutionHandler */

$form = ActiveForm::begin(['id' => 'addPatientForm', 'options' => ['class' => 'form-horizontal bg-default', 'enctype' => 'multipart/form-data'], 'enableAjaxValidation' => false, 'validateOnSubmit'  => false, 'action' => ['/execution/add']]);

try {
//    echo $form->field($model, 'executionNumber', ['template' =>
//        '<div class="col-sm-5">{label}</div><div class="col-sm-7"><div class="input-group">{input}<a id="pasteFromClipboard" class="btn btn-default input-group-addon"><span class="text-success">Вставить</span></a></div>{error}{hint}</div>','inputOptions' =>
//    ['autofocus' => 'autofocus', 'class' => 'form-control', 'tabindex' => '1']])
//        ->textInput(['autocomplete' => 'off', 'focus' => true])
//        ->hint('Номер обследования пациента')
//        ->label('Номер обследования');
    echo $form->field($model, 'executionNumber', ['template' =>
        '<div class="col-sm-5">{label}</div><div class="col-sm-7">{input}{error}{hint}</div>','inputOptions' =>
    ['autofocus' => 'autofocus', 'class' => 'form-control', 'tabindex' => '1']])
        ->textInput(['autocomplete' => 'off', 'focus' => true])
        ->hint('Номер обследования пациента')
        ->label('Номер обследования');

/*    echo $form->field($model, 'executionResponse', ['template' =>
        '<div class="col-sm-5">{label}</div><div class="col-sm-7">{input}{error}{hint}</div>'])->widget(FileInput::class, [
        'options' => ['accept' => 'application/pdf'],
        'pluginOptions' => [
            'browseClass' => 'btn btn-primary btn-block',
            'browseIcon' => '<i class="glyphicon glyphicon-camera"></i> ',
            'browseLabel' =>  'Выберите файл заключения',
            'showUpload' => false
        ]
    ]);
    echo $form->field($model, 'executionData', ['template' =>
        '<div class="col-sm-5">{label}</div><div class="col-sm-7">{input}{error}{hint}</div>'])->widget(FileInput::class, [
        'options' => ['accept' => 'application/zip'],
        'pluginOptions' => [
            'browseClass' => 'btn btn-primary btn-block',
            'browseIcon' => '<i class="glyphicon glyphicon-camera"></i> ',
            'browseLabel' =>  'Выберите файл с данными обследования',
            'showUpload' => false
        ]
    ]);*/
} catch (Exception $e) {
}

echo Html::submitButton('Сохранить', ['class' => 'btn btn-success   ', 'id' => 'addSubmit', 'data-toggle' => 'tooltip', 'data-placement' => 'top', 'data-html' => 'true',]);
ActiveForm::end();

?>
<script><?=file_get_contents(Yii::getAlias('@webroot') . '/js/addExecution.js')?></script>
