<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmQuotationItem extends Model
{
    use HasFactory;

    protected $table = 'crm_quotation_items';

    protected $fillable = [
        'quotation_id',
        'item_id',
        'description',
        'quantity',
        'uom_id',
        'unit_price',
        'direct_cost_unit',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:2',
        'direct_cost_unit' => 'decimal:2',
        'line_total'       => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(CrmQuotation::class, 'quotation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function costBreakups(): HasMany
    {
        return $this->hasMany(CrmQuotationCostBreakup::class, 'quotation_item_id')
            ->orderBy('sort_order');
    }
}
