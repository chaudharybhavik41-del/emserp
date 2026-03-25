<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDispatchLine extends Model
{
    use HasFactory;

    protected $table = 'production_v2_dispatch_lines';

    protected $fillable = [
        'dispatch_id',
        'assembly_id',
        'qty',
        'weight_kg',
        'assembly_code_snapshot',
        'assembly_name_snapshot',
        'girder_no_snapshot',
        'segment_no_snapshot',
        'client_dispatch_part_count',
        'client_dispatch_part_codes_snapshot',
        'client_dispatch_description_snapshot',
        'remarks',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'weight_kg' => 'decimal:3',
        'client_dispatch_part_count' => 'integer',
    ];

    public function dispatch()
    {
        return $this->belongsTo(ProductionDispatch::class, 'dispatch_id');
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }
}
