<?php

namespace app\widgets;

use app\models\database\Wish;
use app\models\Telegram;
use Yii;
use yii\bootstrap\Widget;
use yii\db\Expression;


class ContrastColorWidget extends Widget
{
    public ?string $content = '';

    /**
     * {@inheritdoc}
     */
    public function run(): ?string
    {
        if(empty($this->content) || trim($this->content) === '--' || mb_strtolower(trim($this->content)) === 'не проводилось'){
            return $this->content;
        }
        return "<b class='text-warning'>$this->content</b>";
    }
}
