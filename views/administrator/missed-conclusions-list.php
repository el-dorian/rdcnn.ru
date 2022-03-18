<?php

use app\models\ExecutionHandler;
use app\models\User;
use app\models\Utils;
use yii\web\View;

/* @var $this View */

echo '<h1 class="text-center">Необходимо распечатать заключения!</h1>';

// получу список обследований, у которых нет заключения, основываясь на фильтрах
$patientsList = User::findAllRegistered();
if(!empty($patientsList)){
    foreach ($patientsList as $item) {
        if(!empty(Yii::$app->session['center']) && Yii::$app->session['center'] !== 'all' && Utils::isFiltered($item)){
            continue;
        }
        if(!ExecutionHandler::isConclusion($item->username)){
            echo "<h2 class='text-center'>{$item->username}</h2>";
        }
    }
    echo '<script>print()</script>';
}
