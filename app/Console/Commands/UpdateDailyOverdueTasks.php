<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessOverdueTasks; 

class UpdateDailyOverdueTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-overdue-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches a job to update overdue tasks daily.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProcessOverdueTasks::dispatch();
        $this->info('Overdue tasks processing job dispatched successfully.');
    }
}
