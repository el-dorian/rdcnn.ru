<?php

/* @var $this yii\web\View */

$this->title = 'My Yii Application';
?>
<div class="site-index">

<?php

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL,"http://veldor.online/api/login");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS,
    "postvar1=value1&postvar2=value2&postvar3=value3");

// In real life you should use something like:
// curl_setopt($ch, CURLOPT_POSTFIELDS,
//          http_build_query(array('postvar1' => 'value1')));

// Receive server response ...
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$server_output = curl_exec($ch);

if($server_output)
    echo $server_output;
else{
    echo curl_error($ch);
}

curl_close ($ch);

$isGuest = Yii::$app->user->isGuest;
if(!$isGuest){
    $id = Yii::$app->user->id;
    echo "user $id logged";
}
else{
    echo "nobody logged";
}
/*try {
    echo Yii::$app->security->generateRandomString() . "<br/>";
    $password = 'Xyiman123';
    $hash = Yii::$app->getSecurity()->generatePasswordHash($password);
    echo $hash;
} catch (Exception $e) {
}*/
?>

</div>
