<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmQuotationCostBreakup extends Model
{
    use HasFactory;

    protected $table = 'crm_quotation_cost_breakups';

    protected $fillable = [
        'quotation_item_id',
        'component_code',
        'component_name',
        'basis',
        'rate',
        'sort_order',
    ];

    protected $casts = [
        'rate'       => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(CrmQuotationItem::class, 'quotation_item_id');
    }
}
