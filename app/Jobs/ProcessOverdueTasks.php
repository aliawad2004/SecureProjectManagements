<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class ProcessOverdueTasks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing overdue tasks job started.');

        $overdueTasks = Task::where('due_date', '<', now())
                            ->whereNotIn('status', ['completed', 'cancelled'])
                            ->get();

        foreach ($overdueTasks as $task) {
            $task->status = 'overdue';
            $task->save();
            Log::info("Task #{$task->id} ({$task->name}) marked as overdue.");
        }

        Log::info('Processing overdue tasks job finished. Total overdue tasks updated: ' . $overdueTasks->count());
    }
}