<?php

/* @var $this View */

use app\assets\AdminAsset;
use app\models\database\Emails;
use app\models\ExecutionHandler;
use app\models\Table_availability;
use app\models\User;
use app\models\Utils;

//use dixonstarter\pdfprint\Pdfprint;
//use edgardmessias\assets\nprogress\NProgressAsset;
use nirvana\showloading\ShowLoadingAsset;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;
use yii\widgets\ActiveForm;

AdminAsset::register($this);
ShowLoadingAsset::register($this);
//NProgressAsset::register($this);

$this->title = 'Администрирование';

//echo Pdfprint::widget([
//    'elementClass' => '.btn-pdfprint'
//]);

/* @var $this View */
/* @var $executions User[] */
/* @var $model ExecutionHandler */

$centers = ['all' => '', 'nv' => '', 'aurora' => '', 'ct' => ''];

$days = ['all' => '', 'today' => '', 'yesterday' => ''];

$sort = ['byTime' => '', 'byNumber' => '', 'byExecutions' => '', 'byConclusion' => ''];

if (Utils::isCenterFiltered()) {
    $center = Yii::$app->session['center'];
    foreach ($centers as $key => $value) {
        if ($key === $center) {
            $centers[$key] = 'selected';
        } else {
            $centers[$key] = '';
        }
    }
}
if (Utils::isTimeFiltered()) {
    $time = Yii::$app->session['timeInterval'];
    foreach ($days as $key => $value) {
        if ($key === $time) {
            $days[$key] = 'selected';
        } else {
            $days[$key] = '';
        }
    }
}
$sortBy = Yii::$app->session['sortBy'];
foreach ($sort as $key => $value) {
    if ($key === $sortBy) {
        $sort[$key] = 'selected';
    } else {
        $sort[$key] = '';
    }
}
echo '<div class="row"><div class="col-xs-12 text-center margin">
          <!--<div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="darkSwitch">
            <label class="custom-control-label" for="darkSwitch">Ночной режим</label>
          </div>-->
          <div class="pull-right"><a href="' . Url::toRoute('site/management') . '"><span class="glyphicon glyphicon-cog"></span></a></div>
        </div>';

// добавлю ссылку на поиск обследований
echo "<div class='col-xs-12 text-center margin'><a target='_blank' class='btn btn-info' href='/patient_search'><span class='glyphicon glyphicon-search'></span> Поиск обследований</a></div>";

// добавлю кнопку для создания нового обследования
echo "<div class='col-xs-12 text-center'>";

$form = ActiveForm::begin(['id' => 'addPatientForm', 'options' => ['class' => 'form-horizontal bg-default', 'enctype' => 'multipart/form-data'], 'enableAjaxValidation' => false, 'validateOnSubmit' => false, 'action' => ['/execution/add']]);

try {
    echo $form->field($model, 'executionNumber', ['template' =>
        '<div class="col-sm-offset-4 col-sm-4 col-xs-12"><div class="input-group">{input}<span class="input-group-btn"><button type="submit" class="btn btn-success">Добавить пациента</button></span></div>{error}{hint}</div>', 'inputOptions' =>
        ['class' => 'form-control', 'tabindex' => '1']])
        ->textInput(['autocomplete' => 'off', 'focus' => true])
        ->hint('Номер обследования пациента')
        ->label('Номер обследования');
} catch (Exception $e) {
}

ActiveForm::end();

echo "</div>
<div class='text-center col-xs-12 margin visible-lg visible-md'>
<div class='btn-group'><button class='btn btn-info activator' data-action='/next/nv'>Добавить следующего пациента НВ</button><button class='btn btn-danger activator' data-action='/next/aurora'>Добавить следующего пациента Авроры</button><button class='btn btn-success activator' data-action='/next/ct'>Добавить следующего пациента КТ</button></div>
</div>
<div class='text-center col-xs-12 margin visible-sm visible-xs'>
<div class='btn-group-vertical'><button class='btn btn-info activator' data-action='/next/nv'>Добавить следующего пациента НВ</button><button class='btn btn-danger activator' data-action='/next/aurora'>Добавить следующего пациента Авроры</button></div>
";

echo "</div><div class='col-xs-12'>";

/** @noinspection SpellCheckingInspection */
echo Html::beginForm(['/iolj10zj1dj4sgaj45ijtse96y8wnnkubdyp5i3fg66bqhd5c8']);

echo "<div class='col-sm-4 col-xs-12'><label class='control-label' for='#centerSelect'>Центр</label><select id='centerSelect' name='center' onchange='this.form.submit();' class='form-control'><option value='all'>Все</option><option value='nv' {$centers['nv']}>Нижневолжская набережная</option><option value='aurora' {$centers['aurora']}>Аврора</option><option value='ct' {$centers['ct']}>КТ</option></select></div>";

echo "<div class='col-sm-4 col-xs-12'><label class='control-label' for='#centerSelect'>Время</label><select name='timeInterval' onchange='this.form.submit();' class='form-control'><option value='all'>Всё время</option><option value='today' {$days['today']}>Сегодня</option><option value='yesterday' {$days['yesterday']}>Вчера</option></select></div>";

echo "<div class='col-sm-4 col-xs-12'><label class='control-label' for='#sortBy'>Сортировать по</label><select name='sortBy' onchange='this.form.submit();' class='form-control'><option value='byTime' {$sort['byTime']}>Времени добавления</option><option value='byNumber' {$sort['byNumber']}>Номеру обследования</option><option value='byConclusion' {$sort['byConclusion']}>Наличию заключения</option><option value='byExecutions' {$sort['byExecutions']}>Наличию файлов</option></select></div>";

echo Html::endForm();

echo '</div>';


echo "<div class='col-xs-12 margin'><label>Отслеживать изменения <input type='checkbox' id='showChangesSwitcher'></label></div>";

echo "
    <div class='col-xs-12 margin'> <div class='col-xs-4 text-center'>Всего обследований: <b class='text-info'><span id='patientsCount'>0</span></b></div><div class='col-xs-4 text-center'>Без заключений: <b class='text-danger'><span id='withoutConclusions'>0</span></b></div><div class='col-xs-4 text-danger text-center'>Без файлов: <b class='text-danger'><span id='withoutExecutions'>0</span></b></div></div>
";

$executionsCounter = 0;

if (!empty($executions)) {
    echo "<table class='table-hover table'>
<thead>
<tr>
<th><span class='visible-md visible-lg'>Номер обследования</span><span class='visible-xs visible-sm'>№</span></th>
<th><span class='visible-md visible-lg'>Действия</span><span class='visible-xs visible-sm'></span></th>
<th><span class='visible-md visible-lg'>Загружено заключение</span><span class='visible-xs visible-sm glyphicon glyphicon-file'></span></th>
<th><span class='visible-md visible-lg'>Загружены файлы</span><span class='visible-xs visible-sm glyphicon glyphicon-folder-close'></span></th>
</tr></thead><tbody id='executionsBody'>";
    foreach ($executions as $execution) {
        // проверю, если включена фильтрация по центру- выведу только те обследования, которые проведены в этом центре
        if (Utils::isCenterFiltered() && Utils::isFiltered($execution)) {
            continue;
        }
        ++$executionsCounter;

        $patientName = Table_availability::getPatientName($execution->username);

        $itemText = "<tr class='patient' data-id='{$execution->username}'>";
        if ($patientName !== null) {
            $itemText .= "<td>
                            <a class='btn-link execution-id tooltip-enabled' href='/person/{$execution->username}' data-toggle='tooltip' data-placement='auto' title='{$patientName}'>$execution->username</a>
                            <span class='pull-right'>" . Utils::showDate($execution->created_at) . "</span>
                        </td>";
        } else {
            $itemText .= "<td>
                            <a class='btn-link execution-id' href='/person/{$execution->username}' >$execution->username</a>
                            <span class='pull-right'>" . Utils::showDate($execution->created_at) . "</span>
                        </td>";
        }

        if (Emails::checkExistent($execution->id)) {
            $mailInfo = Emails::findOne(['patient_id' => $execution->id]);
            $hint = $mailInfo->mailed_yet ? 'Отправить письмо<br/>(уже отправлялось)' : 'Отправить письмо';
            $color = $mailInfo->mailed_yet ? ' text-danger' : 'text-info';
            $itemText .= "<td class='mail-td'><button class='btn btn-default tooltip-enabled activator' data-action='/send-info-mail/{$execution->id}' data-toggle='tooltip' data-placement='auto' data-html='true' title='$hint'><span class='glyphicon glyphicon-circle-arrow-right $color'></span></button><button class='btn btn-default add-mail tooltip-enabled' data-action='/mail/add/{$execution->id}' data-toggle='tooltip' data-placement='auto' title='Изменить электронную почту'><span class='glyphicon glyphicon-envelope text-info'></span></button></td>";
        } else {
            $itemText .= "<td class='mail-td'><button class='btn btn-default add-mail tooltip-enabled' data-action='/mail/add/{$execution->id}' data-toggle='tooltip' data-placement='auto' title='Добавить электронную почту'><span class='glyphicon glyphicon-envelope text-success'></span></button></td>";
        }
        if (ExecutionHandler::isConclusion($execution->username)) {
            $conclusionText = ExecutionHandler::getConclusionText($execution->username);
            $itemText .= "<td data-conclusion='$execution->username' class='field-success'><span class='glyphicon glyphicon-ok text-success status-icon' data-toggle='tooltip' data-container='body' data-placement='auto' title='" . Table_availability::getConclusionAreas($execution->username) . "'></span><button class='btn btn-default activator tooltip-enabled' data-action='/delete/conclusions/{$execution->username}' data-toggle='tooltip' data-placement='auto' title='Удалить все заключения по обследованию'><span class='glyphicon glyphicon-trash text-danger'></span></button><button class='btn btn-default tooltip-enabled printer'  data-toggle='tooltip' data-placement='auto' title='Распечатать копию' data-names='$conclusionText'><span class='text-info glyphicon glyphicon-print'></span></button></td>";
        } else {
            $itemText .= "<td data-conclusion='$execution->username' class='field-danger'><span class='glyphicon glyphicon-remove text-danger status-icon'></span></td>";
        }
        if (ExecutionHandler::isExecution($execution->username)) {
            $itemText .= "<td data-execution='$execution->username' class='field-success'><span class='glyphicon glyphicon-ok text-success status-icon'></span></td>";
        } else {
            $itemText .= "<td data-execution='$execution->username' class='field-danger'><span class='glyphicon glyphicon-remove text-danger status-icon'></span></td>";
        }
        $itemText .= "<td>
                <a class='btn btn-default custom-activator' data-action='change-password'
                   data-id='{$execution->username}' data-toggle='tooltip' data-placement='auto'
                   title='Сменить пароль'><span class='text-info glyphicon glyphicon-retweet'></span></a>
                <a class='btn btn-default custom-activator' data-action='delete' data-id='{$execution->username}'
                   data-toggle='tooltip' data-placement='auto' title='Удалить запись'><span
                            class='text-danger glyphicon glyphicon-trash'></span></a>
            </td>";
        $itemText .= "</tr>";
        echo $itemText;
    }
    echo '</tbody></table>';
}
if ($executionsCounter === 0) {
    echo "<div id='noExecutionsRegistered' class='col-xs-12'><h2 class='text-center'>Обследований не зарегистрировано</div>";
}

echo "<div class='col-xs-12 text-center'>";

echo '</div>';
?>

<label><textarea class="hidden" id="forPasswordCopy"></textarea></label>

