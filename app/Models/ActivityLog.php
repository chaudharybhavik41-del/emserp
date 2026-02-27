<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'subject_name',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Action constants
     */
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    const ACTION_RESTORED = 'restored';
    const ACTION_FORCE_DELETED = 'force_deleted';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_PASSWORD_CHANGED = 'password_changed';
    const ACTION_ROLE_ASSIGNED = 'role_assigned';
    const ACTION_ROLE_REMOVED = 'role_removed';
    const ACTION_PERMISSION_ASSIGNED = 'permission_assigned';
    const ACTION_PERMISSION_REMOVED = 'permission_removed';
    const ACTION_ACTIVATED = 'activated';
    const ACTION_DEACTIVATED = 'deactivated';
    const ACTION_EXPORTED = 'exported';
    const ACTION_IMPORTED = 'imported';
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';

    /**
     * User who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The subject of the activity (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log an activity.
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): self {
        $user = Auth::user();
		$changedFields = null;

		// Calculate changed fields (supports nested arrays)
	if (is_array($oldValues) && is_array($newValues)) {
    $changed = [];

    // Keys present in new (updated) values
    foreach ($newValues as $key => $newValue) {
        $oldValue = $oldValues[$key] ?? null;

        // Strict comparison works fine for scalars, arrays, etc.
        if ($oldValue !== $newValue) {
            $changed[] = $key;
        }
    }

    // Keys that existed before but were removed
    foreach ($oldValues as $key => $oldValue) {
        if (!array_key_exists($key, $newValues)) {
            $changed[] = $key;
        }
    }

    $changedFields = $changed ?: null;
	}

        // Generate description if not provided
        if (!$description && $subject) {
            $modelName = class_basename($subject);
            $subjectName = $subject->name ?? $subject->title ?? $subject->code ?? "#{$subject->id}";
            $description = ucfirst($action) . " {$modelName}: {$subjectName}";
        }

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'subject_name' => $subject?->name ?? $subject?->title ?? $subject?->code ?? null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log model creation.
     */
    public static function logCreated(Model $model, ?string $description = null): self
    {
        return self::log(
            self::ACTION_CREATED,
            $model,
            $description,
            null,
            $model->toArray()
        );
    }

    /**
     * Log model update.
     */
    public static function logUpdated(Model $model, array $oldValues, ?string $description = null): self
    {
        return self::log(
            self::ACTION_UPDATED,
            $model,
            $description,
            $oldValues,
            $model->toArray()
        );
    }

    /**
     * Log model deletion.
     */
    public static function logDeleted(Model $model, ?string $description = null): self
    {
        return self::log(
            self::ACTION_DELETED,
            $model,
            $description,
            $model->toArray(),
            null
        );
    }

    /**
     * Log model restoration.
     */
    public static function logRestored(Model $model, ?string $description = null): self
    {
        return self::log(
            self::ACTION_RESTORED,
            $model,
            $description
        );
    }

    /**
     * Log custom action.
     */
    public static function logCustom(
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $metadata = null
    ): self {
        return self::log($action, $subject, $description, null, null, $metadata);
    }

    /**
     * Scope for specific action.
     */
    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific subject type.
     */
    public function scopeForSubjectType($query, string $type)
    {
        return $query->where('subject_type', $type);
    }

    /**
     * Scope for specific subject.
     */
    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id);
    }

    /**
     * Scope for specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_RESTORED => 'Restored',
            self::ACTION_FORCE_DELETED => 'Permanently Deleted',
            self::ACTION_LOGIN => 'Logged In',
            self::ACTION_LOGOUT => 'Logged Out',
            self::ACTION_PASSWORD_CHANGED => 'Changed Password',
            self::ACTION_ROLE_ASSIGNED => 'Role Assigned',
            self::ACTION_ROLE_REMOVED => 'Role Removed',
            self::ACTION_ACTIVATED => 'Activated',
            self::ACTION_DEACTIVATED => 'Deactivated',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Get action color for UI.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'green',
            self::ACTION_UPDATED => 'blue',
            self::ACTION_DELETED, self::ACTION_FORCE_DELETED => 'red',
            self::ACTION_RESTORED => 'purple',
            self::ACTION_LOGIN => 'green',
            self::ACTION_LOGOUT => 'gray',
            self::ACTION_ACTIVATED => 'green',
            self::ACTION_DEACTIVATED => 'orange',
            self::ACTION_APPROVED => 'green',
            self::ACTION_REJECTED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted changes for display.
     */
    public function getFormattedChangesAttribute(): array
    {
        if (!$this->changed_fields || !$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        foreach ($this->changed_fields as $field) {
            // Skip sensitive fields
            if (in_array($field, ['password', 'remember_token'])) {
                continue;
            }

            $changes[] = [
                'field' => str_replace('_', ' ', ucfirst($field)),
                'old' => $this->old_values[$field] ?? null,
                'new' => $this->new_values[$field] ?? null,
            ];
        }

        return $changes;
    }
}
