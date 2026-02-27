<?php

namespace App\Models\Accounting;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Support\MoneyHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class Voucher extends Model
{
    use HasFactory;

    /**
     * Guardrail: prevent posting unbalanced vouchers.
     *
     * We validate when the status is changed to "posted" (draft → posted),
     * because voucher lines are usually inserted BEFORE final posting.
     */
    protected static function booted(): void
    {
        static::updating(function (Voucher $voucher) {
            if (! $voucher->isDirty('status')) {
                return;
            }

            if (($voucher->status ?? null) !== 'posted') {
                return;
            }

            // Ensure posted_at / posted_by are always populated for posted vouchers
            // (addresses legacy data where status='posted' but posted_at is NULL).
            if (empty($voucher->posted_at)) {
                $voucher->posted_at = now();
            }
            if (empty($voucher->posted_by)) {
                $voucher->posted_by = Auth::id();
            }

            [$debitPaise, $creditPaise] = $voucher->totalsInPaise();

            // No-line / zero-value vouchers should never be posted.
            if ($debitPaise === 0 && $creditPaise === 0) {
                throw new RuntimeException('Cannot post a voucher without lines/amounts.');
            }

            if ($debitPaise !== $creditPaise) {
                $diff = MoneyHelper::fromPaise($debitPaise - $creditPaise);
                throw new RuntimeException('Voucher is not balanced (Dr != Cr). Difference (Dr - Cr): ' . $diff);
            }

            // Keep amount_base consistent with the actual voucher lines.
            $voucher->amount_base = MoneyHelper::fromPaise(max($debitPaise, $creditPaise));
        });
    }

    protected $fillable = [
        'company_id',
        'voucher_no',
        'voucher_type',
        'voucher_date',
        'reference',
        'narration',
        'project_id',
        'cost_center_id',
        'currency_id',
        'exchange_rate',
        'amount_base',
        'status',
        'created_by',
        'posted_by',
        'posted_at',

        // Phase 6: reversals
        'reversal_of_voucher_id',
        'reversal_voucher_id',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'amount_base'   => 'decimal:2',
        'posted_at'     => 'datetime',

        // Phase 6
        'reversed_at'   => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class);
    }

    /**
     * Get total debit and total credit in minor units (paise).
     *
     * @return array{0:int,1:int} [debitPaise, creditPaise]
     */
    public function totalsInPaise(): array
    {
        $row = $this->lines()
            ->selectRaw('COALESCE(SUM(debit),0) as debit_total, COALESCE(SUM(credit),0) as credit_total')
            ->first();

        $debitPaise = MoneyHelper::toPaise($row?->debit_total ?? 0);
        $creditPaise = MoneyHelper::toPaise($row?->credit_total ?? 0);

        return [$debitPaise, $creditPaise];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return ! empty($this->reversed_at) || ! empty($this->reversal_voucher_id);
    }
}
