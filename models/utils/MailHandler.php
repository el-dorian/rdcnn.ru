<?php


namespace app\models\utils;


use app\models\database\Emails;
use app\models\database\MailingSchedule;
use app\models\database\PatientInfo;
use app\models\database\TempDownloadLinks;
use app\models\Table_availability;
use app\models\User;
use app\priv\Info;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\helpers\Url;

class MailHandler extends Model
{
    public static function getMailText($text): string
    {
        return Yii::$app->controller->renderPartial('/site/mail-template', ['text' => $text]);
    }

    public static function getMailSantaText($text): string
    {
        return Yii::$app->controller->renderPartial('/site/mail-template-santa', ['text' => $text]);
    }

    public static function getLightMailText($text): string
    {
        return Yii::$app->controller->renderPartial('/site/mail-template-light', ['text' => $text]);
    }

    /**
     * @param $id
     * @return array
     * @throws Exception
     */
    public static function sendInfoMail($id): array
    {
        $user = User::findIdentity($id);
        if ($user !== null) {
            // первым делом, получу данные о почте
            $mail = Emails::findOne(['patient_id' => $id]);
            if ($mail !== null) {
                // получу имя получателя, если оно есть
                $patientName = Table_availability::getPatientName($user->username);

                if ($patientName !== null) {
                    $patientPersonals = ', ' . GrammarHandler::handlePersonals($patientName);
                } else {
                    $patientPersonals = '';
                }

                // отправлю сообщение о доступных данных
                $text = '
                <h3 class="text-center">Здравствуйте ' . $patientPersonals . '</h3>
                <p class="text-center">
                Спасибо, что выбрали нас для прохождения обследования МРТ.
                Результаты обследования будут доступны в личном кабинете.
                 <div class="text-center"><a class="btn btn-info fit-down" href="https://rdcnn.ru/enter/' . $user->getAccessToken() . '">перейти в личный кабинет</a></div>
                <b class="notice">Пароль</b> для входа в личный кабинет находится в <b class="notice">акте оказанных медицинских услуг</b>.
                </p>
            ';
                // проверю наличие снимков
                $existentExecution = Table_availability::findOne(['is_execution' => 1, 'userId' => $user->username]);
                if ($existentExecution !== null) {
                    // проверю существование файла
                    $path = Info::EXEC_FOLDER . DIRECTORY_SEPARATOR . $existentExecution->file_name;
                    if (is_file($path)) {
                        // проверю, существует ли уже ссылка на файл
                        $existentLink = TempDownloadLinks::findOne(['execution_id' => $id, 'file_type' => 'execution', 'file_name' => $existentExecution->file_name]);
                        if ($existentLink === null) {
                            // создам ссылку
                            $existentLink = TempDownloadLinks::createLink($user, 'execution', $existentExecution->file_name);
                        }
                        $text .= "<p class='text-center'>
                                        Доступен для скачивания архив обследования- 
                                        <div class='text-center'><a class='btn btn-info' href='" . Url::toRoute(['download/download-temp', 'link' => $existentLink->link], 'https') . "'>скачать архив обследования</a></div>
                                  </p>";
                    }
                }
                $attachments = [];
                // проверю наличие заключения
                $existentConclusions = Table_availability::findAll(['is_conclusion' => 1, 'userId' => $user->username]);
                if ($existentConclusions !== null) {
                    foreach ($existentConclusions as $existentConclusion) {
                        // проверю существование файла
                        $path = Info::CONC_FOLDER . DIRECTORY_SEPARATOR . $existentConclusion->file_name;
                        if (is_file($path)) {
                            // проверю, существует ли уже ссылка на файл
                            $existentLink = TempDownloadLinks::findOne(['execution_id' => $id, 'file_type' => 'conclusion', 'file_name' => $existentConclusion->file_name]);
                            if ($existentLink === null) {
                                // создам ссылку
                                $existentLink = TempDownloadLinks::createLink($user, 'conclusion', $existentConclusion->file_name);
                            }
                            // получу данные по заключению
                            if (!empty($existentConclusion->execution_area)) {
                                $text .= "<p class='text-center'>
                                        Доступно заключение врача ({$existentConclusion->execution_area})<div class='text-center'> <a class='btn btn-info' href='" . Url::toRoute(['download/download-temp', 'link' => $existentLink->link], 'https') . "'>скачать заключение</a>
                                  </div></p>";
                                $attachments[] = [$path, $existentConclusion->execution_area . ".pdf"];
                            } else {
                                $text .= "<p class='text-center'>
                                        Доступно заключение врача 
                                        <div class='text-center'><a class='btn btn-info' href='" . Url::toRoute(['download/download-temp', 'link' => $existentLink->link], 'https') . "'>скачать заключение</a></div>
                                  </p>";

                                $attachments[] = [$path, "Заключение.pdf"];
                            }
                        }
                    }
                }
                if (self::sendMessage('Информация о пройденном обследовании', $text, $mail->address, $patientName, $attachments)) {
                    // Отмечу, что на данный адрес уже отправлялось письмо
                    if ($mail->mailed_yet === 0) {
                        $mail->mailed_yet = 1;
                        $mail->save();
                    }
                    return ['status' => 1, 'message' => 'Сообщение отправлено'];
                }
            }
        }

        return ['status' => 1, 'message' => 'Не найден адрес почты'];
    }

    public static function sendMessage($title, $text, $address, $sendTo, $attachments, $isLight = false, $santa = false): bool
    {
        $settingsFile = Yii::$app->basePath . '\\priv\\mail_settings.conf';
        if (is_file($settingsFile)) {
            // получу данные
            $content = file_get_contents($settingsFile);
            $settingsArray = mb_split("\n", $content);
            if (count($settingsArray) === 3) {
                $mailList = GrammarHandler::extractEmails($address);
                if (!empty($mailList)) {
                    $recipientsList = [];
                    foreach ($mailList as $mailItem) {
                        $recipientsList[$mailItem] = $sendTo ?? '';
                    }
                    if ($isLight) {
                        $text = self::getLightMailText($text);
                        $mailInfo = PatientInfo::findOne(['email' => $address]);
                        if ($mailInfo !== null) {
                            $text = str_replace('{patient_unsubscribe_token}', $mailInfo->unsubscribe_token, $text);
                        }
                    } else if ($santa) {
                        $text = self::getMailSantaText($text);
                    } else {
                        $text = self::getMailText($text);
                    }
                    // отправлю письмо
                    $mail = Yii::$app->mailer->compose()
                        ->setFrom([$settingsArray[0] => 'Региональный диагностический центр'])
                        ->setSubject($title)
                        ->setHtmlBody($text)
                        ->setTo($recipientsList);
                    // попробую отправить письмо, в случае ошибки- вызову исключение
                    if (!empty($attachments)) {
                        foreach ($attachments as $attachment) {
                            $mail->attach($attachment[0], ['fileName' => $attachment[1]]);
                        }
                    }
                    $mail->send();
                }
                return true;
            }
        }
        return false;
    }

    public static function sendMailing(): array
    {
        $id = trim(Yii::$app->request->post('id'));
        if (!empty($id)) {
            $mailingItem = MailingSchedule::findOne(['id' => $id]);
            if ($mailingItem !== null) {
                try {
                    self::sendMessage(
                        $mailingItem->title,
                        $mailingItem->text,
                        $mailingItem->address,
                        $mailingItem->name,
                        null,
                        true);
                    $mailingItem->delete();
                    sleep(1);
                    return ['status' => 1];
                } catch (\Exception|Throwable $e) {
                    return ['message' => 'Отправка не удалась, текст ошибки- "' . $e->getMessage() . '"'];
                }
            }
        }
        return ['message' => 'Не найден идентификатор сообщения.'];
    }
}