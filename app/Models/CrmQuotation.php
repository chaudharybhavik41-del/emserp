<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmQuotation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_quotations';

    protected $fillable = [
        'code',
        'revision_no',
        'lead_id',
        'party_id',
        'project_name',
        'client_po_number',

        // Pricing controls
        'quote_mode',      // item | rate_per_kg
        'is_rate_only',    // true = do not compute totals (rate-only offer)
        'profit_percent',  // profit % on direct cost (unit basis)

        // Scope / exclusions (esp. for rate-per-kg quotations)
        'scope_of_work',
        'exclusions',

        'status',
        'total_amount',
        'tax_amount',
        'grand_total',
        'valid_till',
        'revision_reason',

        'payment_terms',
        'payment_terms_days',
        'freight_terms',
        'delivery_terms',
        'other_terms',
        'project_special_notes',
        'standard_term_id',
        'terms_text',

        'sent_at',
        'accepted_at',
        'rejected_at',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'total_amount'       => 'decimal:2',
        'tax_amount'         => 'decimal:2',
        'grand_total'        => 'decimal:2',
        'valid_till'         => 'date',
        'sent_at'            => 'datetime',
        'accepted_at'        => 'datetime',
        'rejected_at'        => 'datetime',
        'payment_terms_days' => 'integer',
        'is_rate_only'       => 'boolean',
        'profit_percent'     => 'decimal:2',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }


    public function standardTerm(): BelongsTo
    {
        return $this->belongsTo(\App\Models\StandardTerm::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrmQuotationItem::class, 'quotation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getDisplayCodeAttribute(): string
    {
        return $this->code . ' Rev ' . $this->revision_no;
    }

    public function getIsRatePerKgAttribute(): bool
    {
        return ($this->quote_mode ?? 'item') === 'rate_per_kg';
    }
}
