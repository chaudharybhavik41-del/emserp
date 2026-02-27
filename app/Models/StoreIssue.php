<?php

namespace App\Models;

use App\Models\Concerns\HasPostingStatus;
use App\Models\Accounting\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreIssue extends Model
{
    use HasPostingStatus;

    protected $table = 'store_issues';

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date',
        'accounting_posted_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class, 'store_requisition_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StoreIssueLine::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    /*
     |--------------------------------------------------------------------------
     | Accounting Posting Helpers
     |--------------------------------------------------------------------------
     */

    public function isPostedToAccounts(): bool
    {
        return ! empty($this->voucher_id) || ($this->accounting_status ?? null) === 'posted';
    }

    public function isAccountsPostingNotRequired(): bool
    {
        return ($this->accounting_status ?? null) === 'not_required';
    }

    /*
     |--------------------------------------------------------------------------
     | Posting Guardrails
     |--------------------------------------------------------------------------
     | Store Issue is treated as posted from creation (status = posted).
     | We lock the header fields that must not change once the issue exists.
     */

    public function getPostingLockedAttributes(): array
    {
        return [
            'project_id',
            'store_requisition_id',
            'contractor_party_id',
            'contractor_person_name',
            'issued_to_user_id',
            // we *don’t* lock remarks here so minor text notes can still be added
        ];
    }
}
