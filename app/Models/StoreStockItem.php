<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreStockItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_client_material'  => 'boolean',
        'qty_pcs_total'       => 'integer',
        'qty_pcs_available'   => 'integer',
        'weight_kg_total'     => 'float',
        'weight_kg_available' => 'float',
    ];

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(MaterialReceiptLine::class, 'material_receipt_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function issueLines(): HasMany
    {
        return $this->hasMany(StoreIssueLine::class, 'store_stock_item_id');
    }
}
