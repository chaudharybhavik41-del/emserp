<?php

namespace App\Models;

use App\Models\Accounting\Voucher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreReturn extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date',
        'accounting_posted_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(StoreIssue::class, 'store_issue_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StoreReturnLine::class, 'store_return_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function gatePass(): BelongsTo
    {
        return $this->belongsTo(GatePass::class, 'gate_pass_id');
    }

    public function isPostedToAccounts(): bool
    {
        return ! empty($this->voucher_id) || ($this->accounting_status ?? null) === 'posted';
    }

    public function isAccountsPostingNotRequired(): bool
    {
        return ($this->accounting_status ?? null) === 'not_required';
    }

}