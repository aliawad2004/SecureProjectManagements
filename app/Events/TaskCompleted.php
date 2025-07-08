<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Task;
use App\Models\User;

class TaskCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $assignee;
    public $completer; 

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, User $assignee = null, User $completer = null)
    {
        $this->task = $task;
        $this->assignee = $assignee;
        $this->completer = $completer;
    }
}