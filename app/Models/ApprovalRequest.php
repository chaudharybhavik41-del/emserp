<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\User;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'sub_module',
        'action',
        'permission_name',
        'approvable_type',
        'approvable_id',
        'status',
        'current_step',
        'requested_by',
        'requested_at',
        'closed_by',
        'closed_at',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'closed_at'    => 'datetime',
        'metadata'     => 'array',
    ];

    /**
     * Polymorphic relation to the underlying document
     * (PurchaseIndent, PurchaseOrder, Voucher, etc.).
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User who raised this approval request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * User who finally closed the request
     * (approved / rejected / cancelled).
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * All workflow steps for this request.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)
            ->orderBy('step_number');
    }

    /**
     * Convenience: get the current step model if any.
     */
    public function currentStep(): ?ApprovalStep
    {
        if (! $this->relationLoaded('steps')) {
            $this->load('steps');
        }

        if ($this->current_step) {
            $step = $this->steps->firstWhere('step_number', $this->current_step);
            if ($step) {
                return $step;
            }
        }

        return $this->steps->firstWhere('status', 'pending')
            ?: $this->steps->first();
    }

    /**
     * Scope for a particular module / sub-module.
     */
    public function scopeForModule($query, string $module, ?string $subModule = null)
    {
        $query->where('module', $module);

        if ($subModule !== null) {
            $query->where('sub_module', $subModule);
        }

        return $query;
    }

    /**
     * Scope only pending / in-progress approvals.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }
  	
  	/**
     * Scope: approval requests where the given user can approve at least one
     * pending step.
     *
     * This is usually what you'll use for "My pending approvals" on dashboard.
     */
    public function scopePendingForApprover($query, User $user)
    {
        $userId  = $user->id;
        $roleIds = $user->roles()->pluck('id')->all();

        return $query
            ->pending()
            ->whereHas('steps', function ($stepQuery) use ($userId, $roleIds) {
                $stepQuery->where('status', 'pending')
                    ->where(function ($q) use ($userId, $roleIds) {
                        $q->where('approver_user_id', $userId);

                        if (! empty($roleIds)) {
                            $q->orWhereIn('approver_role_id', $roleIds);
                        }
                    });
            });
    }

    /**
     * (Optional) Scope: any approval requests that this user is/was involved in
     * as an approver in ANY step (not only pending).
     */
    public function scopeForApprover($query, User $user)
    {
        $userId  = $user->id;
        $roleIds = $user->roles()->pluck('id')->all();

        return $query->whereHas('steps', function ($stepQuery) use ($userId, $roleIds) {
            $stepQuery->where(function ($q) use ($userId, $roleIds) {
                $q->where('approver_user_id', $userId);

                if (! empty($roleIds)) {
                    $q->orWhereIn('approver_role_id', $roleIds);
                }
            });
        });
    }

    public function markApproved(?int $userId = null): void
    {
        $this->status    = 'approved';
        $this->closed_by = $userId;
        $this->closed_at = now();
        $this->save();
    }

    public function markRejected(?int $userId = null, ?string $remarks = null): void
    {
        $this->status    = 'rejected';
        $this->closed_by = $userId;
        $this->closed_at = now();

        if ($remarks !== null) {
            $this->remarks = $remarks;
        }

        $this->save();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
