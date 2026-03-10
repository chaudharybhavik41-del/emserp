<?php

namespace App\Models;

use App\Models\Accounting\Voucher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelIssue extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date',
        'qty' => 'decimal:3',
        'unit_rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'opening_meter_reading' => 'decimal:3',
        'closing_meter_reading' => 'decimal:3',
        'accounting_posted_at' => 'datetime',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StoreStockItem::class, 'store_stock_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

