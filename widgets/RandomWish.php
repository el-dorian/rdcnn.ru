<?php

namespace app\widgets;

use app\models\database\Wish;
use app\models\Telegram;
use Yii;
use yii\bootstrap\Widget;
use yii\db\Expression;


class RandomWish extends Widget
{


    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $query = Wish::find()
            ->orderBy(new Expression('rand()'))
            ->limit(1)
            ->one();
        if (!empty($query)) {
            echo "<div class=\"jumbotron\">
  <h1 class=\"display-2\">$query->wish</h1>
  <p class=\"lead\">Мы не знаем чьё это желание и не модерируем их(желания), так что не удивляйтесь :) (серьёзно, не знаем и не узнаем)</p>
  <hr class=\"my-2\">
  <p class=\"lead\">
    <a class=\"btn btn-success btn-lg\" href=\"/santa\" role=\"button\"><span class='glyphicon glyphicon-refresh'></span> Другое</a>
  </p>
</div>";
        }
        else{
            Telegram::sendDebug("Желаний пока нет");
            echo "<div class=\"jumbotron\">
  <h1 class=\"display-2\">Пока никто ничего не загадал</h1>
  <p class=\"lead\">Тут будет отображаться чьё-то случайное желание. Мы не знаем чьё и не модерируем их, так что не удивляйтесь :) (серьёзно, не знаем и не узнаем)</p>
  <hr class=\"my-2\">
  <p class=\"lead\">
    <a class=\"btn btn-primary btn-lg\" href=\"/secret-santa/wish\" role=\"button\">Загадай первым</a>
  </p>
</div>";
        }
    }
}
