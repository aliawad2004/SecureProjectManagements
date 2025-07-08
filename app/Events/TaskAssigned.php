<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Task;
use App\Models\User; 

class TaskAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $assignee;
    public $assigner; // The user who assigned the task

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, User $assignee, User $assigner = null)
    {
        $this->task = $task;
        $this->assignee = $assignee;
        $this->assigner = $assigner;
    }
}
