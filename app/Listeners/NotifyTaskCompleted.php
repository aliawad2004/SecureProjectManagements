<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class NotifyTaskCompleted implements ShouldQueue
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
    public function handle(TaskCompleted $event): void
    {
        $task = $event->task;
        $assignee = $event->assignee;
        $completer = $event->completer;

        Log::info("Listener: NotifyTaskCompleted triggered for task '{$task->name}'.");

        $notifiableUsers = collect();

        if ($task->project->created_by_user_id && (!$completer || $task->project->created_by_user_id !== $completer->id) && (!$assignee || $task->project->created_by_user_id !== $assignee->id)) {
            $notifiableUsers->push($task->project->creator);
        }

        $task->project->members()->wherePivot('role', 'project_manager')->get()->each(function ($pm) use ($notifiableUsers, $completer, $assignee) {
            if ((!$completer || $pm->id !== $completer->id) && (!$assignee || $pm->id !== $assignee->id) && !$notifiableUsers->contains('id', $pm->id)) {
                $notifiableUsers->push($pm);
            }
        });

        if ($task->project->team->owner_id && !$notifiableUsers->contains('id', $task->project->team->owner_id)) {
            if ((!$completer || $task->project->team->owner_id !== $completer->id) && (!$assignee || $task->project->team->owner_id !== $assignee->id)) {
                $notifiableUsers->push($task->project->team->owner);
            }
        }


        $notifiableUsers = $notifiableUsers->unique('id')->filter(function ($user) {
            return $user !== null; 
        });
        Log::info("NotifyTaskCompleted Listener: Handling event for Task ID: {$task->id}");
        Log::info("NotifyTaskCompleted Listener: Assignee for task: " . ($assignee ? $assignee->email : 'N/A'));
        Log::info("NotifyTaskCompleted Listener: Completer for task: " . ($completer ? $completer->email : 'N/A'));
        foreach ($notifiableUsers as $user) {
            $user->notify(new TaskCompletedNotification($task, $assignee, $completer));
            Log::info("Task completed notification dispatched to user #{$user->id} for task '{$task->name}'.");
        }
    }
}
