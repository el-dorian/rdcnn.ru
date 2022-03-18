<?php

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */

/* @var $exception Exception */

use yii\helpers\Html;

Yii::$app->session->open();
Yii::$app->session->destroy();
$this->title = 'Ошибка';
if(Yii::$app->user->can('manage')) {
    ?>
    <div class="site-error">

        <h1><?= Html::encode($this->title) ?></h1>

        <div class="alert alert-danger">
            <?= nl2br(Html::encode($message)) ?>
        </div>

        <p>
            The above error occurred while the Web server was processing your request.
        </p>
        <p>
            Please contact us if you think this is a server error. Thank you.
        </p>

    </div>
    <?php
}
else{
    ?>
    <h1 class="text-center text-danger">Страница не найдена</h1>
    <p>Возможно, истекло время хранения ваших данных или произошла какая-то ошибка. Вы можете <a href="/" class="btn btn-default"><span class="glyphicon glyphicon-log-in text-success"></span><span class="text-success"> повторно зайти</span></a> или позвонить нам <a href="tel:+78312020200" class="btn btn-default"><span class="glyphicon glyphicon-earphone text-success"></span><span class="text-success"> +7(831)20-20-200</span></a></p>
<?php
}
