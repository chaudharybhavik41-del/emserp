<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Attachment;
use App\Models\Concerns\HasPostingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseBill extends Model
{
    use HasFactory;
    use HasPostingStatus;

    protected $table = 'purchase_bills';

    // You were already using guarded = [] pattern in most models
    protected $guarded = [];

    protected $casts = [
        'bill_date'     => 'date',
        'posting_date'  => 'date',
        'due_date'      => 'date',
        'total_basic'   => 'float',
        'total_discount'=> 'float',
        'total_tax'     => 'float',
        'total_amount'  => 'float',
        'total_cgst'    => 'float',
        'total_sgst'    => 'float',
        'total_igst'    => 'float',
        'total_rcm_tax' => 'float',
        'total_rcm_cgst'=> 'float',
        'total_rcm_sgst'=> 'float',
        'total_rcm_igst'=> 'float',
        'tds_rate'      => 'float',
        'tds_amount'    => 'float',
        'tcs_rate'      => 'float',
        'tcs_amount'    => 'float',
        'round_off'     => 'float',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id');
    }


    public function supplierBranch(): BelongsTo
    {
        return $this->belongsTo(PartyBranch::class, 'supplier_branch_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseBillLine::class);
    }

    public function expenseLines(): HasMany
    {
        return $this->hasMany(PurchaseBillExpenseLine::class, 'purchase_bill_id')
            ->orderBy('line_no')
            ->orderBy('id');
    }

    public function tdsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tds_account_id');
    }

    public function tcsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tcs_account_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
            ->orderByDesc('id');
    }

    /*
     |--------------------------------------------------------------------------
     | Posting Guardrails
     |--------------------------------------------------------------------------
     | These are the fields that become read-only once status = 'posted'.
     | This matches plan: lock supplier, bill date/number, amounts, GST, TDS/TCS, project/PO.
     */

    public function getPostingLockedAttributes(): array
    {
        return [
            'supplier_id',
            'supplier_branch_id',
            'bill_date',
            'posting_date',
            'bill_number',
            'purchase_order_id',
            'project_id',

            // Totals & GST
            'total_basic',
            'total_discount',
            'total_tax',
            'total_amount',
            'total_cgst',
            'total_sgst',
            'total_igst',
            'total_rcm_tax',
            'total_rcm_cgst',
            'total_rcm_sgst',
            'total_rcm_igst',

            // TDS
            'tds_rate',
            'tds_amount',
            'tds_section',
            'tds_account_id',

            // TCS
            'tcs_rate',
            'tcs_amount',
            'tcs_section',
            'tcs_account_id',
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Generate next bill number.
     *
     * Format: PB/<FY>/<0001>
     * Example: PB/2025-26/0001
     */
    public static function generateNextBillNumber(?int $companyId = null, $billDate = null): string
    {
        $companyId = (int) ($companyId ?: 1);

        $date = $billDate
            ? \Carbon\Carbon::parse($billDate)
            : now();

        // Indian FY: starts April by default (configurable)
        $startMonth = (int) config('accounting.financial_year.start_month', 4);
        $fyStartYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
        $fyEndYear   = $fyStartYear + 1;
        $fyCode      = sprintf('%d-%02d', $fyStartYear, $fyEndYear % 100);

        $prefix = 'PB/' . $fyCode . '/';

        $lastNumber = static::query()
            ->where('company_id', $companyId)
            ->where('bill_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('bill_number');

        $seq = 1;
        if ($lastNumber && preg_match('/(\d+)$/', (string) $lastNumber, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function getGrossPayableAttribute(): float
    {
        return (float) ($this->total_amount ?? 0)
            + (float) ($this->tcs_amount ?? 0);
    }

    public function getNetPayableAttribute(): float
    {
        return (float) $this->gross_payable
            - (float) ($this->tds_amount ?? 0);
    }
}






