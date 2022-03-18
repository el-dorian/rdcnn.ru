<?php


namespace app\models\utils;


use app\models\FileUtils;
use app\priv\Info;
use Google\Client;
use Google_Client;
use Google_Exception;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use GuzzleHttp\Psr7\Response;
use JsonException;
use RuntimeException;
use Yii;
use yii\console\Exception;

class Gdrive
{

    public static function requireToken()
    {
        $client = new Google_Client();
        $client->setApplicationName('RDC remote');
        $client->setScopes(Google_Service_Drive::DRIVE);
        $client->setAuthConfig(dirname(__DIR__) . '\\..\\priv\\credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $tokenPath = dirname(__DIR__) . '\\..\\priv\\token.json';
        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                if (!mkdir($concurrentDirectory = dirname($tokenPath), 0700, true) && !is_dir($concurrentDirectory)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
    }

    /**
     * @throws Exception
     * @throws Google_Exception|JsonException
     */
    public static function check(): void
    {
        // проверю существование временной папки для заключений
        $tempCloudFolder = Yii::$app->basePath . '\\cloud_tmp';
        if (!is_dir($tempCloudFolder) && !mkdir($tempCloudFolder) && !is_dir($tempCloudFolder)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tempCloudFolder));
        }
        $client = self::getClient();
        if ($client !== null) {
            echo TimeHandler::timestampToDateTime(time()) . "Having client\n";

            // получу список файлов с диска
            $service = new Google_Service_Drive($client);

            // Print the names and IDs for up to 10 files.
            $optParams = array(
                'pageSize' => 100,
            );
            $results = $service->files->listFiles($optParams);

            if (count($results->getFiles()) === 0) {
                print "No files found.\n";
            } else {
                print "TimeHandler::timestampToDate(time()) . Files:\n";
                /** @var Google_Service_Drive_DriveFile $file */
                foreach ($results->getFiles() as $file) {
                    // скачаю и удалю файл
                    self::getFile($service, $file);
                }
                // удалю все файлы из корзины
                $service->files->emptyTrash();
            }
            echo "All files load from Gdrive (" . count($results->getFiles()) . ")\n";
        }
        else{
            echo "No client\n";
        }
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client|null the authorized client object
     * @throws Google_Exception
     * @throws Exception
     * @throws JsonException
     */
    private static function getClient(): ?Google_Client
    {
        $client = new Google_Client();
        $client->setApplicationName('RDC remote');
        $client->setScopes(Google_Service_Drive::DRIVE);
        $client->setAuthConfig(dirname(__DIR__) . '\\..\\priv\\credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = dirname(__DIR__) . '\\..\\priv\\token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true, 512, JSON_THROW_ON_ERROR);
            $client->setAccessToken($accessToken);
            // If there is no previous token or it's expired.
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    $authUrl = $client->createAuthUrl();
                    printf("Open the following link in your browser:\n%s\n", $authUrl);
                    print 'Enter verification code: ';
                    $authCode = trim(fgets(STDIN));

                    // Exchange authorization code for an access token.
                    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                    $client->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new Exception(implode(', ', $accessToken));
                    }
                }
                // Save the token to a file.
                if (!file_exists(dirname($tokenPath)) && !mkdir($concurrentDirectory = dirname($tokenPath), 0700, true) && !is_dir($concurrentDirectory)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken(), JSON_THROW_ON_ERROR, 512));
            }
            return $client;
        }
        return null;
    }

    public static function getFile(Google_Service_Drive $service, Google_Service_Drive_DriveFile $file): void
    {
        try {
            $fileName = $file->getName();
            echo "handle {$fileName}\n";
            // если это .pdf
            if (strlen($fileName) > 4 && substr($fileName, strlen($fileName) - 4) === '.pdf') {
                /** @var Response $response */
                $response = $service->files->get($file->getId(), array(
                    'alt' => 'media'));
                $type = ($response->getHeader('Content-Type'));
                if (!empty($type) && count($type) === 1 && $type[0] === 'application/pdf') {
                    echo TimeHandler::timestampToDateTime(time()) . "handle file {$file->getName()}\n";
                    echo 'saving ' . $file->getName() . "\n";
                    $content = $response->getBody()->getContents();
                    $path = Yii::$app->basePath . '\\cloud_tmp' . '\\' . $file->getName();
                    file_put_contents(Yii::$app->basePath . '/cloud_tmp' . '/' . $file->getName(), $content);
                    // обработаю файл
                    if (is_file($path)) {
                        echo "$path \n";
                        try {
                            $answer = FileUtils::handleFileUpload($path);
                            echo $answer . "\n";
                            if (!is_file($answer)) {
                                echo "not converted {$fileName}\n";
                                rename($path, Info::CONC_FOLDER . '/' . $file->getName());
                            } else {
                                echo "converted {$fileName}\n";
                                if(is_file($path)){
                                    unlink($path);
                                }
                            }
                        } catch (\Exception $e) {
                            echo "Исключение: " . $e->getMessage() . "\n";
                            echo $e->getTraceAsString();
                        }
                    }
                    // удалю файл с облачного диска
                    echo "delete $file->getName()";
                    $service->files->delete($file->getId());
                }
            }
            else{
                // just remove file
                echo "delete $file->getName()";
                $service->files->delete($file->getId());
            }
        } catch (\Exception $e) {
            echo "skipped file {$file->getName()} with error {$e->getMessage()}";
        }
    }

}