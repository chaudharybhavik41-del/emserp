<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\Users\UserCreationAuditContext;

class UserObserver
{
    public function __construct(
        protected UserCreationAuditContext $auditContext
    ) {}

    public function created(User $user): void
    {
        if (! empty($user->password) && ! PasswordHistory::where('user_id', $user->id)->exists()) {
            PasswordHistory::storePassword($user, $user->password);
        }

        $context = $this->auditContext->current();
        $metadata = array_merge($context['metadata'] ?? [], [
            'source' => $context['source'] ?? 'unknown',
            'actor_id' => $context['actor_id'] ?? null,
            'actor_name' => $context['actor_name'] ?? null,
            'request_ip' => $context['ip_address'] ?? null,
            'request_user_agent' => $context['user_agent'] ?? null,
            'execution_context' => $context['execution_context'] ?? null,
        ]);

        ActivityLog::log(
            ActivityLog::ACTION_CREATED,
            $user,
            "Created user via {$metadata['source']}: {$user->name}",
            null,
            $user->toArray(),
            $metadata
        );
    }
}
