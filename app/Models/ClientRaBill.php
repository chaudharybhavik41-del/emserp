<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\Voucher;
use App\Models\Concerns\HasPostingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DEV-4: Client RA Bill / Sales Invoice Model
 * 
 * Per Development Plan v1.2:
 * - Post Client RA Bills / Sales invoices to accounts
 * - Prepare for revenue reporting
 */
class ClientRaBill extends Model
{
    use HasFactory;
    use HasPostingStatus;

    protected $table = 'client_ra_bills';

    protected $guarded = [];

    protected $casts = [
        'bill_date'              => 'date',
        'due_date'               => 'date',
        'period_from'            => 'date',
        'period_to'              => 'date',
        'approved_at'            => 'datetime',
        'einvoice_generated_at'  => 'datetime',
        'gross_amount'           => 'float',
        'previous_amount'        => 'float',
        'current_amount'         => 'float',
        'retention_percent'      => 'float',
        'retention_amount'       => 'float',
        'other_deductions'       => 'float',
        'net_amount'             => 'float',
        'cgst_rate'              => 'float',
        'cgst_amount'            => 'float',
        'sgst_rate'              => 'float',
        'sgst_amount'            => 'float',
        'igst_rate'              => 'float',
        'igst_amount'            => 'float',
        'total_gst'              => 'float',
        'tds_rate'               => 'float',
        'tds_amount'             => 'float',
        'total_amount'           => 'float',
        'receivable_amount'      => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'client_id');
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
        return $this->hasMany(ClientRaBillLine::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Posting Guardrails (per DEV-10)
    |--------------------------------------------------------------------------
    */

    public function getPostingLockedAttributes(): array
    {
        return [
            'client_id',
            'project_id',
            'bill_date',
            'ra_number',
            'invoice_number',
            'gross_amount',
            'previous_amount',
            'current_amount',
            'retention_amount',
            'other_deductions',
            'net_amount',
            'cgst_amount',
            'sgst_amount',
            'igst_amount',
            'total_gst',
            'tds_amount',
            'total_amount',
            'receivable_amount',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByRevenueType($query, string $type)
    {
        return $query->where('revenue_type', $type);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Generate next RA number for company
     */
    public static function generateNextRaNumber(?int $companyId = null): string
    {
        $companyId = $companyId ?? config('accounting.default_company_id', 1);
        $prefix = 'CRA-' . now()->format('y') . '-';

        $lastNumber = static::where('company_id', $companyId)
            ->where('ra_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('ra_number');

        if ($lastNumber) {
            $seq = (int) substr($lastNumber, -4);
            $seq++;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate tax invoice number
     */
    public static function generateNextInvoiceNumber(?int $companyId = null): string
    {
        $companyId = $companyId ?? config('accounting.default_company_id', 1);
        
        // Indian FY: starts 1 April
        $now = now();
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyEndYear = $fyStartYear + 1;
        $fyCode = sprintf('%d-%02d', $fyStartYear, $fyEndYear % 100);
        
        $prefix = 'INV/' . $fyCode . '/';

        $lastNumber = static::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $seq = (int) $matches[1] + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get next RA sequence for this client-project combination
     */
    public static function getNextRaSequence(int $clientId, int $projectId): int
    {
        $lastSeq = static::where('client_id', $clientId)
            ->where('project_id', $projectId)
            ->max('ra_sequence');

        return ($lastSeq ?? 0) + 1;
    }

    /**
     * Calculate totals from line items
     */
    public function recalculateTotals(): void
    {
        $currentAmount = $this->lines()->sum('current_amount');
        $previousAmount = $this->lines()->sum('previous_amount');

        $this->previous_amount = $previousAmount;
        $this->current_amount = $currentAmount;
        $this->gross_amount = $previousAmount + $currentAmount;

        // Net = Current - Deductions
        $totalDeductions = $this->retention_amount + $this->other_deductions;
        $this->net_amount = $currentAmount - $totalDeductions;

        // GST on net amount (Output GST)
        $this->total_gst = $this->cgst_amount + $this->sgst_amount + $this->igst_amount;

        // Total invoice amount = Net + GST
        $this->total_amount = $this->net_amount + $this->total_gst;

        // Receivable = Total - TDS (TDS will be deducted by client)
        $this->receivable_amount = $this->total_amount - $this->tds_amount;
    }

    /**
     * Check if bill can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'submitted'
            && $this->current_amount > 0
            && $this->lines()->exists();
    }

    /**
     * Check if bill can be posted
     */
    public function canBePosted(): bool
    {
        return $this->status === 'approved'
            && is_null($this->voucher_id);
    }

    /**
     * Get revenue type label
     */
    public function getRevenueTypeLabelAttribute(): string
    {
        return match ($this->revenue_type) {
            'fabrication' => 'Fabrication Revenue',
            'erection'    => 'Erection Revenue',
            'supply'      => 'Supply Revenue',
            'service'     => 'Service Revenue',
            'other'       => 'Other Revenue',
            default       => 'Revenue',
        };
    }
}
