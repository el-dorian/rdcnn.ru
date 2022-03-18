<?php

use yii\web\View;

/* @var $this View */
?>

<div class="jumbotron">
  <h1>Запись не найдена!</h1>
  <p>Возможно, истёк срок хранения или вы неправильно ввели номер обследования. Проверьте, пожалуйста, правильность данных и свяжитесь с нами, если не сможете сравиться сами</p>
    <form method="post" action="<?=\yii\helpers\Url::to('/site/logout')?>">
  <p><button class="btn btn-primary btn-lg" type="submit">Войти ещё раз</button></p>
    </form>
</div>