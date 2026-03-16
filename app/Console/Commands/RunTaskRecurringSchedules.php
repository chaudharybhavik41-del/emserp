<?php

namespace App\Console\Commands;

use App\Services\Tasks\TaskRecurringService;
use Illuminate\Console\Command;

class RunTaskRecurringSchedules extends Command
{
    protected $signature = 'tasks:run-recurring';

    protected $description = 'Create tasks for due recurring schedules';

    public function handle(TaskRecurringService $recurringService): int
    {
        $createdTasks = $recurringService->runDueSchedules();

        $this->info("Created {$createdTasks->count()} recurring task(s).");

        return self::SUCCESS;
    }
}
