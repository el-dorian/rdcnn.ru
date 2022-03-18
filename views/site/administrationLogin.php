<?php

use app\models\LoginForm;
use yii\bootstrap\ActiveForm;use yii\helpers\Html;use yii\web\View;



/* @var $this View */
/* @var $model LoginForm */

$this->title = 'Вход для администраторов';
?>

<div class="site-login">
    <h1 class="text-center"><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin([
    'id' => 'login-form',
    'layout' => 'horizontal',
    'fieldConfig' => [
        'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-7\">{error}</div>",
        'labelOptions' => ['class' => 'col-lg-2 control-label'],
    ],
]); ?>

    <?= $form->field($model, 'username', ['template' => "<div class='col-sm-12 col-lg-offset-4 col-lg-4'>{label}</div><div class='col-sm-11 col-lg-offset-4 col-lg-4'>{input} </div><div class='col-lg-1'></div><div class='col-sm-12 text-center'>{error}</div>",])->textInput(['autofocus' => true, 'required' => true])->label("Логин") ?>

    <?= $form->field($model, 'password', ['template' => "<div class='col-sm-12 col-lg-offset-4 col-lg-4'>{label}</div><div class='col-sm-11 col-lg-offset-4 col-lg-4'>{input} </div><div class='col-lg-1'></button></div><div class='col-sm-12 text-center'>{error}</div>",])->passwordInput(['required' => true]) ?>

<div class="form-group">
    <div class="col-lg-12 text-center">
        <?= Html::submitButton('Войти', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
    </div>
</div>

<?php ActiveForm::end(); ?>
</div>
