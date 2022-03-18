<?php


namespace app\models\utils;


use app\models\handlers\XmlToJsonHandler;
use app\models\Telegram;
use JsonException;
use Yii;

class PriceHandler
{
    private string $priceListFile = __DIR__ . '\\..\\..\\prices\\prices.json';

    /**
     * Возвращает JSON с данными о ценах
     * @return false|string
     * @throws JsonException
     */
    public function getPrices()
    {
        // Если загружен список цен-запущу его обновление в фоне и верну его сразу. Если нет- сначала создам, потом верну
        if(is_file($this->priceListFile)){
            $file = Yii::$app->basePath . '\\yii.bat';
            if (is_file($file)) {
                $command = "$file console/load-price-list";
                ComHandler::runCommand($command);
            }
        }
        else{
            $this->loadPrices();
        }
        return json_decode(file_get_contents($this->priceListFile), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public function loadPrices(): void
    {
        $ch = curl_init("https://xn----ttbeqkc.xn--p1ai/prizes.xml");
        # Setup request to send json via POST.
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        # Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        # Send request.
        $result = curl_exec($ch);
        curl_close($ch);
        $parsedData = (new XmlToJsonHandler())->xmlToArr($result);
        $newData = [];
        foreach ($parsedData['root'][0] as $category) {
            foreach ($category as $cat) {
                // нашёл список категорий
                $items = [];
                foreach ($cat['item'] as $item) {
                    $items[] = ['name' => $item['name'], 'price' => $item['price']];
                }
                $newData[] = ['category' => $cat['name'], 'items' => $items];
            }
        }
        file_put_contents($this->priceListFile, json_encode($newData, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
}