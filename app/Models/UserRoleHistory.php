<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class UserRoleHistory extends Model
{
    public $timestamps = false;
    
    protected $table = 'user_role_history';

    protected $fillable = [
        'user_id',
        'role_id',
        'action',
        'performed_by',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    const ACTION_ASSIGNED = 'assigned';
    const ACTION_REMOVED = 'removed';

    /**
     * User whose role was changed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The role that was assigned/removed.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * User who performed the action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Log role assignment.
     */
    public static function logAssigned(User $user, Role $role, ?User $performer = null, ?string $reason = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'action' => self::ACTION_ASSIGNED,
            'performed_by' => $performer?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Log role removal.
     */
    public static function logRemoved(User $user, Role $role, ?User $performer = null, ?string $reason = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'action' => self::ACTION_REMOVED,
            'performed_by' => $performer?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Scope for assignments.
     */
    public function scopeAssignments($query)
    {
        return $query->where('action', self::ACTION_ASSIGNED);
    }

    /**
     * Scope for removals.
     */
    public function scopeRemovals($query)
    {
        return $query->where('action', self::ACTION_REMOVED);
    }
}
