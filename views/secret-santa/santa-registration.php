<?php

use app\assets\SnowAsset;
use app\models\database\Santa;
use app\widgets\RandomWish;
use yii\helpers\Html;
use yii\web\View;

$this->title = 'Тут записывают в Секретных сант';

/* @var $this View */
/* @var $model Santa */

SnowAsset::register($this);
?>

<div class="site-login text-center">

    <div id="ourSmallLogo" class="visible-sm visible-md visible-lg visible-xs"></div>

    <?php
    //    $form = ActiveForm::begin([
    //        'id' => 'login-form',
    //        'layout' => 'horizontal',
    //        'fieldConfig' => [
    //            'labelOptions' => ['class' => 'control-label'],
    //        ],
    //    ]);
    ?>

    <div>
        <h2 class="text-center">Регистрация закончена!</h2>
        <h2 class="text-center">Рассылка адресов будет проведена около 21:00 25 декабря</h2>

        <div class="text-center margin">Участвует: <?= Santa::countSantas() ?></div>
        </div>
        <div class="text-center"><a class="btn btn-default margin"
                                    href="https://ru.wikipedia.org/wiki/%D0%A2%D0%B0%D0%B9%D0%BD%D1%8B%D0%B9_%D0%A1%D0%B0%D0%BD%D1%82%D0%B0">А
                что это?</a></div>
        <div class="text-center"><a class="btn btn-success" href="/secret-santa/wish">Загадать желание</a></div>

        <?php
        try {
            echo RandomWish::widget();
        } catch (Exception $e) {
        }
        ?>
    </div>

