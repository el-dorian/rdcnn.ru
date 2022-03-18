<?php


namespace app\models\selections;


class Task
{
    public int $id;
    public string $initiator;
    public string $target;
    public string $executor;
    public int $task_creation_time;
    public string $task_accept_time;
    public string $task_planned_finish_time;
    public string $task_finish_time;
    public string $task_header;
    public string $task_body;
    public string $task_status;
    public string $executor_comment;
}