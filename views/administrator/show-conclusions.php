<?php


/* @var $this \yii\web\View */
/* @var $archiveList \app\models\database\Archive_complex_execution_info[] */

/* @var $personalList \app\models\Table_availability[] */

use app\priv\Info;

$openedCounter = 0;
if (!empty($archiveList)) {
    foreach ($archiveList as $item) {
        if (file_exists(Info::ARCHIVE_PATH . DIRECTORY_SEPARATOR . $item->pdf_path)) {
            $openedCounter++;
            echo "<script>window.open('/archive-print/$item->execution_identifier');</script>";
        }else {
            echo Info::ARCHIVE_PATH . DIRECTORY_SEPARATOR . $item->pdf_path . '</br>';
        }
    }
} else if (!empty($personalList)) {

    foreach ($personalList as $item) {
        $name = $item->file_name;
        $openedCounter++;
        if (file_exists(Info::CONC_FOLDER . DIRECTORY_SEPARATOR . $name)) {
            echo "<script>window.open('/auto-print/$name');</script>";
        } else {
            echo Info::CONC_FOLDER . DIRECTORY_SEPARATOR . $name . '</br>';
        }
    }
}
if ($openedCounter === 0) {
    echo '<h1>Файлов заключений не найдено</h1>';
} else {
    echo "<script>window.close();</script>";
}

