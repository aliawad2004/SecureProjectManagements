<?php

namespace App\Observers;

use App\Models\Task;
use App\Events\TaskCompleted; 
use Illuminate\Support\Facades\Log;

class TaskObserver
{

    public function created(Task $task): void
    {
        Log::info("Observer: Task '{$task->name}' (ID: {$task->id}) was created.");

    }


    public function updated(Task $task): void
    {
        if ($task->getOriginal('status') !== $task->status && $task->status === 'completed') {
            $task->load('project.creator', 'project.team.owner', 'project.members', 'assignee');
            event(new TaskCompleted($task, $task->assignee, $task->project->creator));
            Log::info("Observer: Task '{$task->name}' (ID: {$task->id}) status changed to 'completed'. TaskCompleted event dispatched.");
        }
        Log::info("Observer: Task '{$task->name}' (ID: {$task->id}) was updated.");
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        Log::info("Observer: Task '{$task->name}' (ID: {$task->id}) was deleted.");
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        Log::info("Observer: Task '{$task->name}' (ID: {$task->id}) was restored.");
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        Log::info("Observer: Task '{$task->name}' (ID: {$task->id}) was force deleted.");
    }
}
