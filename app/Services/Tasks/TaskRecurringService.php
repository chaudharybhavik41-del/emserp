<?php

namespace App\Services\Tasks;

use App\Models\Tasks\Task;
use App\Models\Tasks\TaskRecurringSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TaskRecurringService
{
    public function __construct(
        protected TaskNotificationService $taskNotificationService,
        protected TaskAutomationService $taskAutomationService,
    ) {
    }

    public function runDueSchedules(?CarbonInterface $now = null): Collection
    {
        $now ??= now();

        $createdTasks = collect();

        $schedules = TaskRecurringSchedule::query()
            ->active()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->with(['taskList', 'template'])
            ->get();

        foreach ($schedules as $schedule) {
            $task = $this->createTaskFromSchedule($schedule, $now);
            $createdTasks->push($task);
        }

        return $createdTasks;
    }

    public function createTaskFromSchedule(TaskRecurringSchedule $schedule, ?CarbonInterface $now = null): Task
    {
        $now ??= now();

        $task = null;

        if ($schedule->template) {
            $task = $schedule->template->createTask($schedule->taskList, [
                'title' => $this->parseTemplate($schedule->title_template, $schedule, $now),
                'description' => $schedule->description_template
                    ? $this->parseTemplate($schedule->description_template, $schedule, $now)
                    : null,
                'status_id' => $schedule->status_id ?: $schedule->taskList->default_status_id,
                'priority_id' => $schedule->priority_id ?: $schedule->taskList->default_priority_id,
                'assignee_id' => $schedule->assignee_id,
                'task_type' => $schedule->task_type,
                'estimated_minutes' => $schedule->estimated_minutes,
                'due_date' => $now->copy()->toDateString(),
            ]);
        } else {
            $task = Task::create([
                'company_id' => $schedule->company_id,
                'task_list_id' => $schedule->task_list_id,
                'title' => $this->parseTemplate($schedule->title_template, $schedule, $now),
                'description' => $schedule->description_template
                    ? $this->parseTemplate($schedule->description_template, $schedule, $now)
                    : null,
                'status_id' => $schedule->status_id ?: $schedule->taskList->default_status_id,
                'priority_id' => $schedule->priority_id ?: $schedule->taskList->default_priority_id,
                'assignee_id' => $schedule->assignee_id,
                'task_type' => $schedule->task_type,
                'estimated_minutes' => $schedule->estimated_minutes,
                'due_date' => $now->copy()->toDateString(),
            ]);
        }

        if (!empty($schedule->default_labels)) {
            $task->labels()->sync($schedule->default_labels);
        }

        $schedule->forceFill([
            'last_run_at' => $now,
            'next_run_at' => $this->nextRunAt($schedule, $now),
        ])->save();

        $this->taskNotificationService->notifyAssignment($task->fresh(['assignee', 'taskList', 'project']));
        $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'created');

        return $task;
    }

    public function nextRunAt(TaskRecurringSchedule $schedule, CarbonInterface $from): CarbonInterface
    {
        return match ($schedule->interval_unit) {
            'daily' => $from->copy()->addDays($schedule->interval_value),
            'weekly' => $from->copy()->addWeeks($schedule->interval_value),
            default => $from->copy()->addMonths($schedule->interval_value),
        };
    }

    protected function parseTemplate(string $template, TaskRecurringSchedule $schedule, CarbonInterface $now): string
    {
        return str_replace(
            ['{{date}}', '{{datetime}}', '{{schedule_name}}'],
            [$now->format('Y-m-d'), $now->format('Y-m-d H:i'), $schedule->name],
            $template
        );
    }
}
