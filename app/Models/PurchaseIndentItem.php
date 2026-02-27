<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseIndentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_indent_id',
        'item_id',
        'line_no',
        'origin_type',
        'origin_id',
        'length_mm',
        'width_mm',
        'thickness_mm',
        'density_kg_per_m3',
        'weight_per_meter_kg',
        'weight_per_piece_kg',
        'qty_pcs',
        'order_qty',
        'uom_id',
        'grade',
        'description',
        'remarks',
        // Procurement tracking
        'received_qty_total',
        'receipt_status',
        'is_closed',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'length_mm'          => 'float',
        'width_mm'           => 'float',
        'thickness_mm'       => 'float',
        'density_kg_per_m3'  => 'float',
        'weight_per_meter_kg'=> 'float',
        'weight_per_piece_kg'=> 'float',
        'qty_pcs'            => 'float',
        'order_qty'          => 'float',
        'received_qty_total' => 'float',
        'is_closed'          => 'boolean',
        'closed_at'          => 'datetime',
    ];

    public function indent(): BelongsTo
    {
        return $this->belongsTo(PurchaseIndent::class, 'purchase_indent_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}

