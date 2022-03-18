<?php


namespace app\models;


use app\models\database\MailingSchedule;
use app\models\database\PatientInfo;
use app\models\database\PatientInfoDouble;
use app\models\utils\GrammarHandler;
use app\models\utils\MailHandler;
use DateTime;
use Exception;
use Yii;
use yii\base\Model;

class Utils extends Model
{
    /**
     * Перевод секунд таймера в дату завершения таймера
     * @param $seconds
     * @return string
     * @throws Exception
     */
    public static function secondsToTime($seconds): string
    {
        $dtF = new DateTime('@0');
        $dtT = new DateTime("@$seconds");
        return $dtF->diff($dtT)->format('%a дней, %h часов, %i минут %s секунд');
    }

    /**
     * @return bool
     */
    public static function isCenterFiltered(): bool
    {
        return !empty(Yii::$app->session['center']) && Yii::$app->session['center'] !== 'all';
    }

    /**
     * @param User $execution
     * @return bool
     */
    public static function isFiltered(User $execution): bool
    {
        // если фильтр по авроре- номер должен начинаться с буквы A, если НВ- с цифры
        $firstSymbol = $execution->username[0];
        return !((Yii::$app->session['center'] === 'aurora' && $firstSymbol === 'A') || (Yii::$app->session['center'] === 'ct' && $firstSymbol === 'T') || (Yii::$app->session['center'] === 'nv' && !empty((int)$firstSymbol)));
    }

    public static function getSort(): string
    {
        if (!empty(Yii::$app->session['sortBy'])) {
            return Yii::$app->session['sortBy'];
        }
        return 'byTime';
    }

    /**
     * Проверка наличия фильтра по дате прохождения обследования
     * @return bool
     */
    public static function isTimeFiltered(): bool
    {
        return !empty(Yii::$app->session['timeInterval']) && Yii::$app->session['timeInterval'] !== 'all';
    }

    /**
     * Получение временной метки начала суток
     * @param bool $forToday
     * @return int
     * @throws Exception
     */
    public static function getStartInterval($forToday = false): int
    {
        if ($forToday) {
            $dtNow = new DateTime();
        } else {
            $dtNow = self::setupDay();
        }
        $dtNow->modify('today');
        return $dtNow->getTimestamp();
    }

    /**
     * Получение временной метки завершения суток
     * @param bool $forToday
     * @return int
     * @throws Exception
     */
    public static function getEndInterval($forToday = false): int
    {
        if ($forToday) {
            $dtNow = new DateTime();
        } else {
            $dtNow = self::setupDay();
        }
        $dtNow->modify('today');
        $endOfDay = clone $dtNow;
        $endOfDay->modify('tomorrow');
        $endOfDateTimestamp = $endOfDay->getTimestamp();
        $endOfDay->setTimestamp($endOfDateTimestamp - 1);
        return $endOfDay->getTimestamp();
    }

    /**
     * Сортировка заключений по выбранным параметрам
     * @param array $executionsList
     * @return array
     */
    public static function sortExecutions(array $executionsList): array
    {
        usort(
        /**
         * @param $execution1 User
         * @param $executon2 User
         * @return mixed
         */ $executionsList, static function ($execution1, $execution2) {
            switch (self::getSort()) {
                case 'byNumber':
                    return $execution1->username < $execution2->username ? 1 : 0;
                case 'byExecutions':
                    return ExecutionHandler::isExecution($execution1->username) > ExecutionHandler::isExecution($execution2->username) ? 1 : 0;
                case 'byConclusion':
                    return ExecutionHandler::isConclusion($execution1->username) > ExecutionHandler::isConclusion($execution2->username) ? 1 : 0;
                case 'byTime':
                default:
                    return $execution1->created_at < $execution2->created_at ? 1 : 0;
            }
        });
        return $executionsList;
    }

    public static function showDate(int $timestamp): string
    {
        setlocale(LC_ALL, 'ru_RU.utf8');
        return strftime('%d %h %H:%M', $timestamp);
    }

    /**
     * @return DateTime
     * @throws Exception
     */
    public static function setupDay(): DateTime
    {
        if (Yii::$app->session['timeInterval'] === 'today') {
            $time = time();
        }
        if (Yii::$app->session['timeInterval'] === 'yesterday') {
            $time = time() - 86400;
        }
        $dtNow = new DateTime();
        if (!empty($time)) {
            $dtNow->setTimestamp($time);
        }
        return $dtNow;
    }

    public static function sendTest(): void
    {
        $username = 'Ольга Викторовна';
        $text = "<br/><br/>Добрый день, $username<br/>
В Региональном диагностическом центре открылось отделение <b>компьютерной томографии</b> по адресу:<br/> <b>г. Нижний Новгород, ул. Советская, д.12 (пл. Ленина). </b><br/>
Записаться на исследования вы можете по тел. <a href='tel:88312020200'>+7(831)20-20-200</a>. <br/>
Подробная информация на нашем сайте <a href='http://www.мрт-кт.рф'>www.мрт-кт.рф</a><br/><br/><br/>
<a href='http://xn----ttbeqkc.xn--p1ai/nn/kt'><img class='advice' src='https://rdcnn.ru/images/ct_advice.jpg' alt='ct_advice'></a><br/><br/><br/><br/>
";
        MailHandler::sendMessage('Тест рассылки',
            $text,
            'o.maleeva1973@mail.ru',
            'Ольга Царапкина',
            null,
            true);
        MailHandler::sendMessage('Тест рассылки',
            $text,
            'om@rdcnn.ru',
            'Ольга Царапкина',
            null,
            true);
        MailHandler::sendMessage('Тест рассылки',
            $text,
            'eldorianwin@gmail.com',
            'Ольга Царапкина',
            null
            , true);
    }

    public static function handlePatientsTable(): void
    {
        /** @var PatientInfo $patient */
        foreach (PatientInfoDouble::find()->each(100) as $patient) {
            // проверю, что адреса ещё нет в базе
            if(PatientInfo::find()->where(['email' => $patient->email])->count() === '0'){
                $new = new PatientInfo();
                $new->email = $patient->email;
                $new->name = $patient->name;
                $new->phone = $patient->phone;
                $new->sex = $patient->sex;
                $new->unsubscribe_token = $patient->unsubscribe_token;
                $new->unsubscribed = $patient->unsubscribed;
                $new->save();
            }
//            $address = $patient->email;
//            if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
//                continue;
//            }
//            $multiAccounts = explode(' ', $address);
//            if (!empty($multiAccounts)) {
//                foreach ($multiAccounts as $account) {
//                    if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
//                        (new PatientInfo([
//                            'name' => $patient->name,
//                            'email' => $account,
//                            'unsubscribe_token' => Yii::$app->security->generateRandomString(255),
//                            'phone' => $patient->phone,
//                            'sex' => $patient->sex
//                        ]))->save();
//                    }
//                }
//            }
//            $patient->delete();
//            $username = GrammarHandler::handlePersonals($patient->name);
//            $text = "<br/><br/>Добрый день, $username<br/>
//В Региональном диагностическом центре открылось отделение <b>компьютерной томографии</b> по адресу:<br/> <b>г. Нижний Новгород, ул. Советская, д.12 (пл. Ленина). </b><br/>
//Записаться на исследования вы можете по тел. <a href='tel:88312020200'>+7(831)20-20-200</a>. <br/>
//Подробная информация на нашем сайте <a href='http://www.мрт-кт.рф'>www.мрт-кт.рф</a><br/><br/><br/>
//<a href='http://xn----ttbeqkc.xn--p1ai/nn/kt'><img class='advice' src='https://rdcnn.ru/images/ct_advice.jpg' alt='ct_advice'></a><br/><br/><br/><br/>
//";
//            (new MailingSchedule([
//                'text' => $text,
//                'name' => $patient->name,
//                'title' => 'Открытие отделения компьютерной томографии',
//                'address' => $patient->email
//            ]))->save();
        }
    }

}