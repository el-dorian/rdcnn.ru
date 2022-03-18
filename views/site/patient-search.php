<?php

use app\assets\SearchAsset;
use app\widgets\ContrastColorWidget;
use app\widgets\ModalityColorWidget;
use nirvana\showloading\ShowLoadingAsset;
use yii\helpers\Html;
use yii\widgets\ActiveForm;


/* @var $this \yii\web\View */

/* @var $model \app\models\utils\PatientSearch */

/* @var $results \app\models\selections\SearchResult[] */

SearchAsset::register($this);
ShowLoadingAsset::register($this);


echo '<div class="row">';
$form = ActiveForm::begin(['id' => 'Search', 'options' => ['class' => 'form-horizontal bg-default no-print'], 'enableAjaxValidation' => false, 'action' => ['/patient_search']]);
echo $form->field($model, 'page', ['template' => '{input}'])->hiddenInput()->label(false);
echo $form->field($model, 'executionNumber', ['template' =>
    '<div class="col-lg-6 col-sm-5 text-right">{label}</div><div class="col-lg-6 col-sm-7"> {input}{error}{hint}</div>', 'options' => ['class' => 'form-group col-sm-6 col-lg-5']])
    ->input('text')
    ->label('Номер обследования');
echo $form->field($model, 'patientPersonals', ['template' =>
    '<div class="col-lg-6 col-sm-5 text-right">{label}</div><div class="col-lg-6 col-sm-7"> {input}{error}{hint}</div>', 'options' => ['class' => 'form-group col-sm-6 col-lg-5']])
    ->input('text')
    ->label('ФИО пациента');
echo $form->field($model, 'executionDateStart', ['template' =>
    '<div class="col-lg-6 col-sm-5 text-right">{label}</div><div class="col-lg-6 col-sm-7"> {input}{error}{hint}</div>', 'options' => ['class' => 'form-group col-sm-6 col-lg-5']])
    ->input('date')
    ->label('С');
echo $form->field($model, 'executionDateFinish', ['template' =>
    '<div class="col-lg-6 col-sm-5 text-right">{label}</div><div class="col-lg-6 col-sm-7"> {input}{error}{hint}</div>', 'options' => ['class' => 'form-group col-sm-6 col-lg-5']])
    ->input('date')
    ->label('По');
echo $form->field($model, 'sortBy', ['template' =>
    '<div class="col-lg-6 col-sm-5 text-right">{label}</div><div class="col-lg-6 col-sm-7"> {input}{error}{hint}</div>', 'options' => ['class' => 'form-group col-sm-6 col-lg-5']])
    ->dropDownList([
        0 => 'По времени добавления ↧ ',
        1 => 'По времени добавления ↥ ',
        2 => 'По имени пациента ↧ ',
        3 => 'По имени пациента ↥ ',
        4 => 'По центру ↧ ',
        5 => 'По центру ↥ ',
        6 => 'По доктору ↧ ',
        7 => 'По доктору ↥ ',
        8 => 'По контрасту ↧ ',
        9 => 'По контрасту ↥ ',
        10 => 'По области обследования ↧ ',
        11 => 'По области обследования ↥ ',
    ],
        ['encode' => false])
    ->label('Сортировка');
echo $form->field($model, 'center', ['template' =>
    '<div class="col-lg-6 col-sm-5 text-right">{label}</div><div class="col-lg-6 col-sm-7"> {input}{error}{hint}</div>', 'options' => ['class' => 'form-group col-sm-6 col-lg-5']])
    ->dropDownList([
        0 => 'Все',
        1 => 'Аврора',
        2 => 'НВН',
        3 => 'КТ',
    ],
        ['encode' => false]);


echo "<div class='col-sm-12 text-center margin'>";
echo Html::submitButton('Найти', ['class' => 'btn btn-success btn-sm margin', 'id' => 'addSubmit', 'data-toggle' => 'tooltip', 'data-placement' => 'top', 'data-html' => 'true',]);
echo '<a href="/patient_search" class="btn btn-warning btn-sm">Новый поиск</a>';
echo '</div>';
ActiveForm::end();
echo '</div>';

if (!empty($results)) {
    echo '<table class="table table-condensed table-hover"><tbody>';
    $counter = 1;
    foreach ($results as $result) {
        if ($result->type === 'Архив') {
            $options = "
<li><a href='/print/$result->executionNumber' target='_blank'>Распечатать заключения</a></li>
<li><a href='#' class='activator' data-action='/create/$result->executionNumber'>Добавить пациента в ЛК</a></li>
";
        } else if ($result->type === 'Неактивный') {
            $options = "
<li><a href='/print/$result->executionNumber' target='_blank'>Распечатать заключения</li>
<li><a href='#' class='activator' data-action='/enable/$result->executionNumber'>Восстановить доступ со старым паролем</a></li>
<li><a href='#' class='activator' data-action='/enable/$result->executionNumber/1'>Восстановить доступ и сменить пароль</a></li>
";
        } else {
            $options = "
<li><a href='/print/$result->executionNumber' target='_blank'>Распечатать заключения</li>
";
        }
        /** @noinspection NestedTernaryOperatorInspection */
        echo "<tr><td>$counter</td>
<td>". ModalityColorWidget::widget(['content' => $result->modality]) ."</td>
<td>$result->executionNumber</td>
<td>$result->executionDate</td>
<td>$result->patientPersonals</td>
<td>$result->patientBirthdate</td>
<td>$result->executionAreas</td>
<td>". ContrastColorWidget::widget(['content' => $result->contrastInfo]) ."</td>
<td>$result->diagnostician</td>
<td><button class='btn btn-sm " . ($result->type === 'Активный' ? 'btn-success' : ($result->type === 'Неактивный' ? 'btn-danger' : 'btn-warning')) . "'>$result->type</button></td>
<td><div class=\"btn-group\">
  <button type=\"button\" class=\"btn btn-default dropdown-toggle\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">
    <span class=\"caret\"></span>
  </button>
  <ul class=\"dropdown-menu\">
$options
  </ul>
</div></td>
</tr>";
        $counter++;
    }
    echo '</tbody></table>';
    if ($model->page > 0) {
        echo "<button id='prevPageBtn' class='btn btn-warning' data-page='" . $model->page - 1 . "'>Предыдущие результаты</button>";
    }
    if (count($results) >= 20) {
        echo "<button id='nextPageBtn' class='btn btn-success' data-page='" . $model->page + 1 . "'>Ещё результаты</button>";
    }
} else {
    if ($model->page > 0) {
        echo "<button id='prevPageBtn' class='btn btn-warning' data-page='" . $model->page - 1 . "'>Предыдущие результаты</button>";
    }
    echo "<div class='col-sm-12 text-center margin'>Результатов нет</div>";
}
