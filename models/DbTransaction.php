<?php


namespace app\models;


use Yii;
use yii\db\Exception;
use yii\db\Transaction;

class DbTransaction
{
    /**
     * @var Transaction
     */
    private $transaction;

    public function __construct()
    {
        $db = Yii::$app->db;
        $this->transaction = $db->beginTransaction();
    }

    /**
     * @throws Exception
     */
    public function commitTransaction(){
            $this->transaction->commit();
    }

    /**
     *
     */
    public function rollbackTransaction(){
        $this->transaction->rollBack();
    }
}