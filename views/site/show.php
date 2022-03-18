<?php

/* @var $this View */

use app\assets\ShowAsset;
use yii\helpers\Url;
use yii\web\View;

ShowAsset::register($this);

?>

<script type="text/javascript">
    var params = [];
    params["worldSpace"] = true;
    params["images"] = ['<?= Url::toRoute('download/execution')?>'];
</script>
<div class="papaya"  data-params="params"></div>
