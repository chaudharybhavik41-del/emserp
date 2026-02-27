<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TestSystemAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class NotificationService
{
    /**
     * Send a database alert to a single user.
     * IMPORTANT: never break main flow if notification fails.
     */
    public function sendSystemAlertToUser(
        User $user,
        string $title,
        string $message,
        ?array $meta = null,
        ?string $url = null,
        ?string $level = null,
        string $type = 'system_alert'
    ): void {
        try {
            $user->notify(new TestSystemAlertNotification(
                title: $title,
                message: $message,
                meta: $meta,
                url: $url,
                level: $level,
                type: $type
            ));
        } catch (\Throwable $e) {
            // swallow
        }
    }

    /**
     * Send alert to multiple users.
     */
    public function sendSystemAlertToUsers(
        iterable $users,
        string $title,
        string $message,
        ?array $meta = null,
        ?string $url = null,
        ?string $level = null,
        string $type = 'system_alert'
    ): void {
        $collection = $users instanceof Collection ? $users : collect($users);

        $collection
            ->filter(fn ($u) => $u instanceof User)
            ->unique('id')
            ->each(function (User $u) use ($title, $message, $meta, $url, $level, $type) {
                // Optional safety: skip inactive users if the field exists
                if (isset($u->is_active) && !$u->is_active) {
                    return;
                }

                $this->sendSystemAlertToUser($u, $title, $message, $meta, $url, $level, $type);
            });
    }

    /**
     * Send alert to all users in a role.
     */
    public function sendSystemAlertToRole(
        Role|int $role,
        string $title,
        string $message,
        ?array $meta = null,
        ?string $url = null,
        ?string $level = null,
        string $type = 'system_alert'
    ): void {
        try {
            $roleModel = $role instanceof Role ? $role : Role::find($role);
            if (!$roleModel) {
                return;
            }

            $users = method_exists($roleModel, 'users') ? $roleModel->users : collect();
            $this->sendSystemAlertToUsers($users, $title, $message, $meta, $url, $level, $type);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    public function sendSystemAlertToCurrentUser(
        string $title,
        string $message,
        ?array $meta = null,
        ?string $url = null,
        ?string $level = null,
        string $type = 'system_alert'
    ): void {
        $user = Auth::user();
        if (!$user) return;

        $this->sendSystemAlertToUser($user, $title, $message, $meta, $url, $level, $type);
    }
}