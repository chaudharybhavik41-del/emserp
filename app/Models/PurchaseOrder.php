<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'code',
        'project_id',
        'department_id',
        'vendor_party_id',
        'vendor_branch_id',
        'purchase_rfq_id',
        'purchase_indent_id',
        'po_date',
        'expected_delivery_date',
        'payment_terms_days',
        'delivery_terms_days',
        'freight_terms',
        'total_amount',
        'status',
        'remarks',
        'standard_term_id',
        'terms_text',
        'created_by',
        'approved_by',
    ];

    /**
     * Casts ensure dates are Carbon instances and numbers are numeric.
     * This fixes ajaxPurchaseOrdersForSupplier(), which calls
     * $po->po_date->format('Y-m-d').
     */
    protected $casts = [
        'cancelled_at' => 'datetime',
        'po_date'                => 'date',
        'expected_delivery_date' => 'date',
        'payment_terms_days'     => 'integer',
        'delivery_terms_days'    => 'integer',
        'total_amount'           => 'float',
    ];

    // --- Relationships ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function vendor(): BelongsTo
    {
        // Party model used for vendors
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }


    public function vendorBranch(): BelongsTo
    {
        return $this->belongsTo(PartyBranch::class, 'vendor_branch_id');
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function indent(): BelongsTo
    {
        return $this->belongsTo(PurchaseIndent::class, 'purchase_indent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // --- Helpers ---

    /**
     * Generate next PO code in format: PO-YYYYMM-####.
     */
    public static function generateNextCode(): string
    {
        $prefix = 'PO-' . now()->format('Ym') . '-';

        $last = static::where('code', 'LIKE', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($last && preg_match('/\d+$/', $last->code, $m)) {
            $nextNumber = ((int) $m[0]) + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function standardTerm(): BelongsTo
    {
        return $this->belongsTo(\App\Models\StandardTerm::class);
    }

    public function materialReceipts(): HasMany
    {
        return $this->hasMany(MaterialReceipt::class, 'purchase_order_id');
    }
}


