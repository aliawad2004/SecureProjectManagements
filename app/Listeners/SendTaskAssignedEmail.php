<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\TaskAssignedMail;
use Illuminate\Support\Facades\Log;

class SendTaskAssignedEmail implements ShouldQueue
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

        if ($assignee && $assignee->email) {
            Mail::to($assignee->email)->send(new TaskAssignedMail($task, $assignee)); 
            Log::info("Email for task #{$task->id} assigned to {$assignee->email} sent via queue.");
        } else {
            Log::warning("Could not send task assigned email for task #{$task->id}. Assignee email not found or user does not exist.");
        }
    }
}
