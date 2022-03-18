<?php


namespace app\models\selections;


use yii\base\Model;

class SearchResult extends Model
{
    public string $executionNumber = '';
    public ?string $patientPersonals = null;
    public ?string $patientBirthdate = null;
    public ?string $executionDate = null;
    public ?string $executionAreas = null;
    public ?string $contrastInfo = null;
    public ?string $diagnostician = null;
    public string $type = '';
    public string $modality = '';
}