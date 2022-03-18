<?php


namespace app\models\utils;


use app\models\database\Archive_complex_execution_info;
use app\models\selections\SearchResult;
use app\models\Table_availability;
use app\models\User;
use app\priv\Info;
use DateTime;
use JetBrains\PhpStorm\ArrayShape;
use yii\base\Model;

class PatientSearch extends Model
{
    public ?string $executionNumber = null;
    public ?string $patientPersonals = null;
    public ?string $executionDateStart = null;
    public ?string $executionDateFinish = null;
    public int $sortBy = 0;
    public int $center = 0;
    public ?int $page = 0;


    #[ArrayShape(['executionNumber' => "string", 'patientPersonals' => "string", 'executionDateStart' => "string", 'executionDateFinish' => "string", 'center' => "string"])] public function attributeLabels(): array
    {
        return [
            'executionNumber' => 'Номер обследования',
            'patientPersonals' => 'ФИО пациента',
            'executionDateStart' => 'Дата обследования (с)',
            'executionDateFinish' => 'Дата обследования (по)',
            'center' => 'Центр',
        ];
    }

    public function rules(): array
    {
        return [
            [['executionNumber', 'patientPersonals', 'executionDateStart', 'executionDateFinish', 'page', 'sortBy', 'center'], 'safe'],
            [['executionDateStart', 'executionDateFinish'], 'date', 'format' => 'y-M-d'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function search(): array
    {
        // сначала поищу в ЛК
        $patientResults = [];
        if (!empty($this->patientPersonals)) {
            $personalsResultsRequest = Table_availability::find()->where(['like', 'patient_name', "%$this->patientPersonals%", false]);
            if (!empty($this->executionNumber)) {
                $personalsResultsRequest->andWhere(['userId' => $this->executionNumber]);
            }
            $availabilityResults = $personalsResultsRequest->limit(20)->offset($this->page * 20)->all();
            if (!empty($this->executionDateStart) && !empty($this->executionDateFinish)) {
                $start = new DateTime('0:0:00' . $this->executionDateStart);
                $finish = new DateTime('23:59:50' . $this->executionDateFinish);
                $interval = ['start' => $start->format('U'), 'finish' => $finish->format('U')];
            } else if (!empty($this->executionDateStart)) {
                $start = new DateTime('0:0:00' . $this->executionDateStart);
                $finish = new DateTime('23:59:50' . $this->executionDateStart);
                $interval = ['start' => $start->format('U'), 'finish' => $finish->format('U')];
            }
            if (!empty($availabilityResults)) {
                foreach ($availabilityResults as $availabilityResult) {
                    $user = User::findByUsername($availabilityResult->userId);
                    if ($user !== null) {
                        if (!empty($interval['start']) && $user->created_at < $interval['start']) {
                            continue;
                        }
                        if (!empty($interval['finish']) && $user->created_at > $interval['finish']) {
                            continue;
                        }
                        if (empty($patientResults[$user->username])) {
                            $patientResults[$user->username] = $user;
                        }
                    }
                }
            }
        } else if (!empty($this->executionNumber)) {
            $request = User::find()->where(['username' => $this->executionNumber]);
            if (!empty($this->executionDateStart)) {
                $start = new DateTime('0:0:00' . $this->executionDateStart);
                if (!empty($this->executionDateFinish)) {
                    $finish = new DateTime('23:59:50' . $this->executionDateFinish);
                } else {
                    $finish = new DateTime('23:59:50' . $this->executionDateStart);
                }
                $request
                    ->andWhere(['>=', 'execution_date', $start->format('Y-m-d')])
                    ->andWhere(['<=', 'execution_date', $finish->format('Y-m-d')]);
            }
            $availabilityResults = $request->limit(20)->offset($this->page * 20)->all();
            if (!empty($availabilityResults)) {
                foreach ($availabilityResults as $availabilityResult) {
                    $patientResults[$availabilityResult->username] = $availabilityResult;
                }
            }

        } else if (!empty($this->executionDateStart)) {
            $start = new DateTime('0:0:00' . $this->executionDateStart);
            if (!empty($this->executionDateFinish)) {
                $finish = new DateTime('23:59:50' . $this->executionDateFinish);
            } else {
                $finish = new DateTime('23:59:50' . $this->executionDateStart);
            }
            $availabilityRequest = User::find()
                ->where(['>', 'created_at', $start->format('U')])
                ->andWhere(['<', 'created_at', $finish->format('U')]);
            if ($this->center > 0) {
                switch ($this->center) {
                    case 1:
                        $availabilityRequest->andWhere(['like', 'username', "A%", false]);
                        break;
                    case 2:
                        $availabilityRequest->andWhere(['not like', 'username', "A%", false]);
                        $availabilityRequest->andWhere(['not like', 'username', "T%", false]);
                        break;
                    case 3:
                        $availabilityRequest->andWhere(['like', 'username', "T%", false]);
                        break;
                }
            }


            switch ($this->sortBy) {
                case 0:
                    $availabilityRequest->orderBy('created_at');
                    break;
                case 1:
                    $availabilityRequest->orderBy('created_at DESC');
                    break;
                case 4:
                    $availabilityRequest->orderBy('username');
                    break;
                case 5:
                    $availabilityRequest->orderBy('username DESC');
                    break;
            }

            $availabilityRequest
                ->limit(20)
                ->offset($this->page * 20);
            $availabilityResults = $availabilityRequest->all();
            if (!empty($availabilityResults)) {
                foreach ($availabilityResults as $availabilityResult) {
                    $patientResults[$availabilityResult->username] = $availabilityResult;
                }
            }
        }
        $searchResult = [];
        if (!empty($patientResults)) {
            foreach ($patientResults as $patientResult) {
                $result = new SearchResult();
                $result->executionDate = TimeHandler::timestampToDate($patientResult->created_at);
                /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
                $result->executionNumber = $patientResult->username;
                if (str_starts_with($patientResult->username, 'A')) {
                    $result->modality = "Аврора";
                } else if (str_starts_with($patientResult->username, 'T')) {
                    $result->modality = "КТ";
                } else {
                    $result->modality = "НВН";
                }
                Table_availability::fillSearchResult($patientResult, $result);
                if ($patientResult->updated_at < time() - Info::DATA_SAVING_TIME) {
                    $result->type = 'Неактивный';
                } else {
                    $result->type = 'Активный';
                }
                $searchResult[$result->executionNumber] = $result;
            }
        }
        // теперь добавлю поиск данных пациента в архиве
        $archiveRequest = Archive_complex_execution_info::find();
        if (!empty($this->patientPersonals)) {
            $archiveRequest->where(['like', 'patient_name', "%$this->patientPersonals%", false]);
        }
        if (!empty($this->executionNumber)) {
            $archiveRequest->andWhere(['execution_number' => $this->executionNumber]);
        }

        if (!empty($this->executionDateStart)) {
            $start = new DateTime('0:0:00' . $this->executionDateStart);
            if (!empty($this->executionDateFinish)) {
                $finish = new DateTime('23:59:50' . $this->executionDateFinish);
            } else {
                $finish = new DateTime('23:59:50' . $this->executionDateStart);
            }
            $archiveRequest
                ->andWhere(['>=', 'execution_date', $start->format('Y-m-d')])
                ->andWhere(['<=', 'execution_date', $finish->format('Y-m-d')]);
        }

        if ($this->center > 0) {
            switch ($this->center) {
                case 1:
                    $archiveRequest->andWhere(['like', 'execution_number', "A%", false]);
                    break;
                case 2:
                    $archiveRequest->andWhere(['not like', 'execution_number', "A%", false]);
                    $archiveRequest->andWhere(['not like', 'execution_number', "T%", false]);
                    break;
                case 3:
                    $archiveRequest->andWhere(['like', 'execution_number', "T%", false]);
                    break;
            }
        }
        switch ($this->sortBy) {
            case 0:
                $archiveRequest->orderBy('execution_date');
                break;
            case 1:
                $archiveRequest->orderBy('execution_date DESC');
                break;
            case 2:
                $archiveRequest->orderBy('patient_name');
                break;
            case 3:
                $archiveRequest->orderBy('patient_name DESC');
                break;
            case 4:
                $archiveRequest->orderBy('execution_number');
                break;
            case 5:
                $archiveRequest->orderBy('execution_number DESC');
                break;
            case 6:
                $archiveRequest->orderBy('doctor');
                break;
            case 7:
                $archiveRequest->orderBy('doctor DESC');
                break;
            case 8:
                $archiveRequest->orderBy('contrast_info');
                break;
            case 9:
                $archiveRequest->orderBy('contrast_info DESC');
                break;
            case 10:
                $archiveRequest->orderBy('execution_area');
                break;
            case 11:
                $archiveRequest->orderBy('execution_area DESC');
                break;
        }
        $r = $archiveRequest
            ->limit(20)
            ->offset($this->page * 20)
            ->all();
        if (!empty($r)) {
            /** @var \app\models\database\Archive_complex_execution_info $item */
            foreach ($r as $item) {
                $result = new SearchResult();
                $result->executionNumber = $item->execution_number;;
                $result->executionDate = $item->execution_date;
                $result->patientPersonals = $item->patient_name;
                $result->contrastInfo = $item->contrast_info;
                $result->diagnostician = $item->doctor;
                $result->executionAreas = $item->execution_area;
                $result->patientBirthdate = $item->patient_birthdate;
                $result->type = 'Архив';
                if (str_starts_with($result->executionNumber, 'A')) {
                    $result->modality = "Аврора";
                } else if (str_starts_with($result->executionNumber, 'T')) {
                    $result->modality = "КТ";
                } else {
                    $result->modality = "НВН";
                }
                if (empty($searchResult[$result->executionNumber])) {
                    $searchResult[$result->executionNumber] = $result;
                } else if ($searchResult[$result->executionNumber]->type === 'Архив') {
                    $searchResult[$result->executionNumber]->executionAreas .= $result->executionAreas . "</br>";
                }
            }
        }
        return $searchResult;
    }
}