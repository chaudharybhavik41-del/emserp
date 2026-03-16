<?php

namespace App\Services\Tasks;

use App\Models\Tasks\Task;
use App\Models\Tasks\TaskComment;
use App\Models\User;
use App\Services\NotificationService;

class TaskNotificationService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    public function notifyAssignment(Task $task, ?User $actor = null): void
    {
        $task->loadMissing('assignee', 'taskList', 'project');

        if (!$task->assignee || ($actor && (int) $task->assignee->id === (int) $actor->id)) {
            return;
        }

        $title = 'Task assigned';
        $message = "{$task->task_number} has been assigned to you.";

        $this->notificationService->sendSystemAlertToUser(
            $task->assignee,
            $title,
            $message,
            meta: [
                'task_id' => $task->id,
                'task_number' => $task->task_number,
                'task_title' => $task->title,
                'task_list' => $task->taskList?->name,
                'project' => $task->project?->code,
            ],
            url: route('tasks.show', $task),
            level: 'info',
            type: 'tasks.assignment'
        );
    }

    public function notifyComment(Task $task, TaskComment $comment): void
    {
        $task->loadMissing('watchers', 'assignee', 'reporter');
        $comment->loadMissing('user');

        $recipients = collect([$task->assignee, $task->reporter])
            ->merge($task->watchers)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->reject(fn (User $user) => (int) $user->id === (int) $comment->user_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notificationService->sendSystemAlertToUsers(
            $recipients,
            'Task comment added',
            "{$comment->user?->name} commented on {$task->task_number}.",
            meta: [
                'task_id' => $task->id,
                'task_number' => $task->task_number,
                'task_title' => $task->title,
                'comment_id' => $comment->id,
            ],
            url: route('tasks.show', $task),
            level: 'info',
            type: 'tasks.comment'
        );
    }
}
