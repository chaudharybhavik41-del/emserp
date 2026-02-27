<?php

namespace App\Models\Production;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionBillLine extends Model
{
    use HasFactory;

    protected $table = 'production_bill_lines';

    protected $fillable = [
        'production_bill_id',
        'production_activity_id',
        'qty',
        'qty_uom_id',
        'rate',
        'rate_uom_id',
        'amount',

        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'line_total',

        'source_meta',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'source_meta' => 'array',
    ];

    public function bill()
    {
        return $this->belongsTo(ProductionBill::class, 'production_bill_id');
    }

    public function activity()
    {
        return $this->belongsTo(ProductionActivity::class, 'production_activity_id');
    }

    public function qtyUom()
    {
        return $this->belongsTo(Uom::class, 'qty_uom_id');
    }

    public function rateUom()
    {
        return $this->belongsTo(Uom::class, 'rate_uom_id');
    }
}
