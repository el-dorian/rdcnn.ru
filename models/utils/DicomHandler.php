<?php


namespace app\models\utils;


use Nanodicom_Core;

class DicomHandler
{

    public static function readInfoFromDicomdir()
    {

        $path = 'Z:\dicom\DICOMDIR';
        if(is_file($path)){

            $content = fopen($path, 'r+');
            $string = fread($content, 454);
            echo $string;
            fclose($content);
        }
    }
}