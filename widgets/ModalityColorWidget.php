<?php

namespace app\widgets;

use app\models\database\Wish;
use app\models\Telegram;
use Yii;
use yii\bootstrap\Widget;
use yii\db\Expression;


class ModalityColorWidget extends Widget
{
    public ?string $content = '';

    /**
     * {@inheritdoc}
     */
    public function run(): string
    {
        if(str_starts_with($this->content, 'А')){
            return "<b class='text-success'>$this->content</b>";
        }
        if(str_starts_with($this->content, 'Н')){
            return "<b class='text-info'>$this->content</b>";
        }
        return "<b class='text-warning'>$this->content</b>";
    }
}
