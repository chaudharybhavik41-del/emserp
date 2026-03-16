<?php

namespace App\Console\Commands;

use App\Models\Tasks\Task;
use App\Services\Tasks\TaskAutomationService;
use Illuminate\Console\Command;

class RunTaskOverdueAutomations extends Command
{
    protected $signature = 'tasks:run-overdue-automation';

    protected $description = 'Run overdue task automation rules';

    public function handle(TaskAutomationService $automationService): int
    {
        $tasks = Task::query()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->where('is_archived', false)
            ->whereHas('status', fn ($query) => $query->where('is_closed', false))
            ->get();

        foreach ($tasks as $task) {
            $automationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'overdue');
        }

        $this->info("Processed {$tasks->count()} overdue task(s).");

        return self::SUCCESS;
    }
}
