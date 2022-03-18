<?php


namespace app\models\database;


use app\models\exceptions\MyException;
use app\models\selections\Task;
use app\models\utils\FirebaseHandler;
use yii\db\ActiveRecord;

/**
 * @property int $id [int(10) unsigned]
 * @property int $initiator [int(10) unsigned]
 * @property int $target [int(10) unsigned]
 * @property int $executor [int(10) unsigned]
 * @property int $task_creation_time [int(10) unsigned]
 * @property int $task_accept_time [int(10) unsigned]
 * @property int $task_planned_finish_time [int(10) unsigned]
 * @property int $task_finish_time [int(10) unsigned]
 * @property string $task_header [varchar(255)]
 * @property string $task_body [varchar(255)]
 * @property string $task_status [varchar(255)]
 * @property string $executor_comment [varchar(255)]
 */
class PersonalTask extends ActiveRecord
{

    public static function tableName(): string
    {
        return 'personal_tasks';
    }

    public static function getTaskList(int $id): array
    {
        $existent = self::find()->where(['initiator' => $id])->all();
        $result = [];
        if (!empty($existent)) {
            /** @var PersonalTask $item */
            foreach ($existent as $item) {
                $result[] = self::getTask($item);
            }
        }
        return $result;
    }

    public static function getTasksForExecutor(PersonalItems $user): array
    {
        // получу список задач, которые уже привязаны к данному пользователю, и тех, что относятся
        // к его группе но не привязаны к нему
        $tasks = self::find()->where(['executor' => $user->id])->orWhere(['executor' => null, 'target' => $user->role])->all();
        $result = [];
        if (!empty($tasks)) {
            /** @var PersonalTask $item */
            foreach ($tasks as $item) {
                $result[] = self::getTask($item);
            }
        }
        return $result;
    }


    /**
     * @param PersonalTask $item
     * @return Task
     */
    public static function getTask(PersonalTask $item): Task
    {
        $task = new Task();
        $task->id = $item->id;
        $initiator = PersonalItems::findOne(['id' => $item->initiator]);
        if ($initiator !== null) {
            $task->initiator = $initiator->name;
        }
        if (!empty($item->executor)) {
            $executor = PersonalItems::findOne(['id' => $item->executor]);
            if ($executor !== null) {
                $task->executor = $executor->name;
            }
        } else {
            $task->executor = '';
        }
        /** @var PersonalRoles $target */
        $target = PersonalRoles::findOne($item->target);
        if ($target !== null) {
            $task->target = $target->role;
        }
        $task->task_creation_time = $item->task_creation_time;
        $task->task_accept_time = $item->task_accept_time ?: 0;
        $task->task_planned_finish_time = $item->task_planned_finish_time ?: 0;
        $task->task_finish_time = $item->task_finish_time ?: 0;
        $task->task_header = $item->task_header ?: '';
        $task->task_body = $item->task_body;
        $task->task_status = $item->task_status;
        $task->executor_comment = $item->executor_comment ?: '';
        return $task;
    }

    /**
     * @param $taskId
     * @return Task
     * @throws MyException
     */
    public static function getTaskInfo($taskId): Task
    {
        $item = self::findOne($taskId);
        if ($item !== null) {
            return self::getTask($item);
        }
        throw new MyException("Неверный идентификатор задачи");
    }

    public static function setTaskConfirmed($taskId, $plannedTime, PersonalItems $user): void
    {
        $item = self::findOne($taskId);
        if ($item !== null) {
            $now = time();
            $item->task_accept_time = $now;
            $item->task_planned_finish_time = $now + $plannedTime * 86400;
            $item->executor = $user->id;
            $item->task_status = 'accepted';
            $item->save();
            // отправлю сообщение инициатору о том, что задача принята
            FirebaseHandler::sendTaskAccepted($item);
        }
    }

    public static function setTaskCancelled($taskId): void
    {
        $item = self::findOne($taskId);
        if ($item !== null) {
            $now = time();
            $item->task_finish_time = $now;
            $item->task_status = 'cancelled_by_initiator';
            $item->save();
            FirebaseHandler::sendTaskCancelled($item);
        }
    }

    public static function findNew(PersonalItems $user)
    {
        return self::find()->where(['target' => $user->role, 'task_status' => 'created'])->count();
    }

    public static function setTaskFinished($taskId): void
    {
        $item = self::findOne($taskId);
        if ($item !== null && $item->task_status !== 'finished') {
            $now = time();
            $item->task_finish_time = $now;
            $item->task_status = 'finished';
            $item->save();
            // отправлю сообщение инициатору о том, что задача принята
            FirebaseHandler::sendTaskFinished($item);
        }
    }

    public static function setTaskDismissed($taskId, $reason): void
    {
        $item = self::findOne($taskId);
        if($item !== null){
            $now = time();
            $item->task_finish_time = $now;
            $item->task_status = 'cancelled_by_executor';
            $item->executor_comment = $reason;
            $item->save();
            // отправлю сообщение инициатору о том, что задача отменена
            FirebaseHandler::sendTaskDismissed($item);
        }
    }
}