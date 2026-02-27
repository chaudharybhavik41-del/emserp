<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ProductionAudit
{
    /**
     * Write a production audit log entry.
     *
     * This helper MUST NEVER break the primary workflow.
     */
    public static function log(
        $projectId,
        string $event,
        ?string $entityType = null,
        $entityId = null,
        ?string $message = null,
        array $meta = []
    ): void {
        try {
            $modelClass = 'App\\Models\\Production\\ProductionAuditLog';

            // If the model isn't present in this deployment, do not hard-fail.
            if (!class_exists($modelClass)) {
                return;
            }

            /** @var \Illuminate\Database\Eloquent\Model $modelClass */
            $modelClass::create([
                'project_id'  => $projectId ?: null,
                'event'       => $event,
                'entity_type' => $entityType,
                'entity_id'   => $entityId ?: null,
                'message'     => $message,
                'meta'        => empty($meta) ? null : $meta,
                'user_id'     => Auth::id(),
                'ip_address'  => Request::ip(),
                'user_agent'  => mb_substr((string) Request::userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never block business flow.
            try {
                logger()->warning('ProductionAudit::log failed', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $ignored) {
                // Ignore logging failures too.
            }
        }
    }
}
