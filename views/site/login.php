    <?php

    /* @var $this yii\web\View */
    /* @var $form yii\bootstrap\ActiveForm */
    /* @var $model app\models\LoginForm */

    use app\assets\LoginAsset;
    use yii\bootstrap\ActiveForm;
    use yii\helpers\Html;

    LoginAsset::register($this);

    $this->title = 'РДЦ, личный кабинет';

    ?>
    <div class="site-login text-center">

        <div id="ourLogo" class="visible-sm visible-md visible-lg"></div>
        <div id="ourSmallLogo" class="visible-xs"></div>

        <?php $form = ActiveForm::begin([
            'id' => 'login-form',
            'layout' => 'horizontal',
            'fieldConfig' => [
                'labelOptions' => ['class' => 'control-label'],
            ],
        ]); ?>

            <?= $form->field($model, 'username', ['template' => "<div class='col-xs-12 col-lg-offset-4 col-lg-4'>{label}</div><div class='col-xs-offset-3 col-xs-6 col-lg-offset-4 col-lg-4'>{input} </div><div class='col-xs-1'></div><div class='col-xs-12'>{error}</div>",])->textInput(['autofocus' => true,]) ?>

            <?= $form->field($model, 'password', ['template' => "<div class='col-xs-12 col-lg-offset-4 col-lg-4'>{label}</div><div class='col-xs-offset-3 col-xs-6 col-lg-offset-4 col-lg-4'>{input} </div><div class='col-xs-1'></div><div class='col-xs-12'>{error}</div>",])->passwordInput() ?>

            <div class="form-group">
                <div class="col-sm-12 text-center">
                    <?= Html::submitButton('Вход', ['class' => 'btn btn-primary', 'name' => 'login-button', 'id' => 'submit-login-btn']) ?>
                </div>
            </div>
            <div><a href="tel:+78312020200" class="btn btn-default"><span class="glyphicon glyphicon-earphone text-success"></span><span class="text-success"> +7(831)20-20-200</span></a></div>
        <?php ActiveForm::end(); ?>

    </div>
