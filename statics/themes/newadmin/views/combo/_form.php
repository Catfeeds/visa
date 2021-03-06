<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use \common\models\Type;

/* @var $this yii\web\View */
/* @var $model common\models\Combo */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="product-form">

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <?= $model->isNewRecord ? Yii::t('backend', 'Create'). Yii::t('backend', 'Combo') : Yii::t('backend', 'Edit') . Yii::t('backend', 'Combo') ;?>
            </h3>
        </div>
        <div class="panel-body">
            <div class="col-md-6">

                <?php $form = ActiveForm::begin(); ?>

                <?= $form->field($model, 'combo_classify')->dropDownList(Type::getComboClassify()); ?>

                <?= $form->field($model, 'combo_type')->dropDownList(Type::getComboType()); ?>
                
                <?= $form->field($model, 'combo_name')->textInput() ?>

                <?= $form->field($model, 'combo_cost')->textInput() ?>

                <?= $form->field($model, 'combo_charge')->textInput() ?>

                <?php if($model->isNewRecord) echo $form->field($model, 'product_id')->hiddenInput(['value'=>$productModel->id])->label(false) ?>

                <div class="form-group">
                    <?= Html::submitButton($model->isNewRecord ? Yii::t('backend', 'Create') : Yii::t('backend', 'Update'), ['class' => $model->isNewRecord ? 'btn btn-primary' : 'btn btn-info']) ?>
                    <?= Html::button(Yii::t('backend', 'GoBack'), [
                            'class' => 'btn btn-danger',
                            'onclick' => 'history.back(-1)'
                    ]) ?>
                </div>

                <?php ActiveForm::end(); ?>
        </div>
    </div>


</div>

