<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\Log;

class CreateTaskAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TaskAssigned $event): void
    {
        $task = $event->task;
        $assignee = $event->assignee;
        $assigner = $event->assigner;

        if ($assignee) {
           
            $assignee->notify(new TaskAssignedNotification($task, $assigner));
            Log::info("Internal notification created for task #{$task->id} assigned to user #{$assignee->id}.");
        } else {
            Log::warning("Could not create internal notification for task #{$task->id}. Assignee not found.");
        }
    }
}