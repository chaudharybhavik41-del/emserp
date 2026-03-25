<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionBillSourceLink extends Model
{
    use HasFactory;

    protected $table = 'production_v2_bill_source_links';

    protected $fillable = [
        'production_v2_bill_id',
        'source_type',
        'source_id',
    ];

    public function bill()
    {
        return $this->belongsTo(ProductionBill::class, 'production_v2_bill_id');
    }
}
