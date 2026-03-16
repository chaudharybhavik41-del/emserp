<?php

namespace App\Services\Tasks;

use App\Models\Tasks\Task;
use App\Models\Tasks\TaskAutomationRule;
use App\Models\User;
use App\Services\NotificationService;

class TaskAutomationService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TaskNotificationService $taskNotificationService,
    ) {
    }

    public function handleTaskEvent(Task $task, string $event, array $context = []): void
    {
        $task->loadMissing('taskList', 'watchers', 'assignee', 'project');

        $rules = TaskAutomationRule::query()
            ->forCompany((int) $task->company_id)
            ->active()
            ->where('trigger_event', $event)
            ->where(function ($query) use ($task) {
                $query->whereNull('task_list_id')
                    ->orWhere('task_list_id', $task->task_list_id);
            })
            ->get();

        foreach ($rules as $rule) {
            if (!$this->matchesConditions($rule->conditions ?? [], $task, $context)) {
                continue;
            }

            $this->applyActions($rule->actions ?? [], $task);
        }
    }

    protected function matchesConditions(array $conditions, Task $task, array $context): bool
    {
        foreach ($conditions as $field => $expected) {
            $match = match ($field) {
                'status_id' => (int) $task->status_id === (int) $expected,
                'priority_id' => (int) $task->priority_id === (int) $expected,
                'task_type' => (string) $task->task_type === (string) $expected,
                'is_milestone' => (bool) $task->is_milestone === (bool) $expected,
                'is_blocked' => (bool) $task->is_blocked === (bool) $expected,
                'has_assignee' => (bool) filled($task->assignee_id) === (bool) $expected,
                'title_contains' => str_contains(strtolower($task->title), strtolower((string) $expected)),
                'event_changed_fields' => !empty(array_intersect((array) $expected, (array) ($context['changed_fields'] ?? []))),
                default => true,
            };

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    protected function applyActions(array $actions, Task $task): void
    {
        $shouldNotifyAssignment = false;

        if (!empty($actions['set_status_id'])) {
            $task->status_id = (int) $actions['set_status_id'];
        }

        if (!empty($actions['set_priority_id'])) {
            $task->priority_id = (int) $actions['set_priority_id'];
        }

        if (array_key_exists('assign_user_id', $actions) && filled($actions['assign_user_id'])) {
            $task->assignee_id = (int) $actions['assign_user_id'];
            $shouldNotifyAssignment = true;
        }

        if ($task->isDirty()) {
            $task->saveQuietly();
        }

        if (!empty($actions['add_watcher_ids']) && is_array($actions['add_watcher_ids'])) {
            $watcherIds = User::query()
                ->whereIn('id', $actions['add_watcher_ids'])
                ->pluck('id')
                ->all();

            if ($watcherIds !== []) {
                $task->watchers()->syncWithoutDetaching($watcherIds);
            }
        }

        if (!empty($actions['notify_user_ids']) && is_array($actions['notify_user_ids'])) {
            $users = User::query()->whereIn('id', $actions['notify_user_ids'])->get();

            $this->notificationService->sendSystemAlertToUsers(
                $users,
                'Task automation triggered',
                "{$task->task_number} matched automation rules.",
                meta: [
                    'task_id' => $task->id,
                    'task_number' => $task->task_number,
                ],
                url: route('tasks.show', $task),
                level: 'info',
                type: 'tasks.automation'
            );
        }

        if ($shouldNotifyAssignment) {
            $this->taskNotificationService->notifyAssignment($task->fresh(['assignee', 'taskList', 'project']));
        }
    }
}
