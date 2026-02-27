<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;
use App\Models\User;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_id',
        'step_number',
        'name',
        'is_mandatory',
        'status',
        'status_changed_at',
        'approver_user_id',
        'approver_role_id',
        'acted_by',
        'acted_at',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'is_mandatory'      => 'boolean',
        'status_changed_at' => 'datetime',
        'acted_at'          => 'datetime',
        'metadata'          => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function markApproved(int $userId, ?string $remarks = null): void
    {
        $this->status            = 'approved';
        $this->status_changed_at = now();
        $this->acted_by          = $userId;
        $this->acted_at          = now();

        if ($remarks !== null) {
            $this->remarks = $remarks;
        }

        $this->save();
    }

    public function markRejected(int $userId, ?string $remarks = null): void
    {
        $this->status            = 'rejected';
        $this->status_changed_at = now();
        $this->acted_by          = $userId;
        $this->acted_at          = now();

        if ($remarks !== null) {
            $this->remarks = $remarks;
        }

        $this->save();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
  	    /**
     * Scope: only steps that are still pending.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: steps where the given user is an eligible approver
     * (either directly assigned as approver_user_id or via approver_role_id).
     */
    public function scopeForApprover($query, User $user)
    {
        $userId  = $user->id;
        $roleIds = $user->roles()->pluck('id')->all();

        return $query->where(function ($q) use ($userId, $roleIds) {
            $q->where('approver_user_id', $userId);

            if (! empty($roleIds)) {
                $q->orWhereIn('approver_role_id', $roleIds);
            }
        });
    }

    /**
     * Scope: "my pending approval steps" for a given user.
     * Steps must be pending AND belong to an approval request that is
     * still pending/in_progress.
     */
    public function scopePendingForApprover($query, User $user)
    {
        return $query
            ->pending()
            ->forApprover($user)
            ->whereHas('request', function ($q) {
                $q->whereIn('status', ['pending', 'in_progress']);
            });
    }

  
  
}
