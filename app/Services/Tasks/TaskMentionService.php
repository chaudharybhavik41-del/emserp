<?php

namespace App\Services\Tasks;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TaskMentionService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    public function resolveMentionedUsers(string $content): Collection
    {
        preg_match_all('/@([\w\.-]+)/', $content, $matches);
        $tokens = collect($matches[1] ?? [])->map(fn ($value) => strtolower(trim($value)))->filter()->unique();

        if ($tokens->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (User $user) use ($tokens) {
                $handles = collect([
                    $user->employee_code,
                    $user->email ? Str::before($user->email, '@') : null,
                    $user->name ? Str::slug($user->name) : null,
                    $user->name ? strtolower(str_replace(' ', '', $user->name)) : null,
                ])
                    ->filter()
                    ->map(fn ($value) => strtolower(trim((string) $value)))
                    ->unique();

                return $handles->intersect($tokens)->isNotEmpty();
            })
            ->unique('id')
            ->values();
    }

    public function notifyMentions(Collection $users, string $message, string $url, array $meta = []): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $this->notificationService->sendSystemAlertToUsers(
            $users,
            'You were mentioned in a task',
            $message,
            meta: $meta,
            url: $url,
            level: 'info',
            type: 'tasks.mention'
        );
    }
}
