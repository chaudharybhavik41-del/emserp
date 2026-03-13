<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionRouteTemplateStep extends Model
{
    use HasFactory;

    protected $table = 'production_v2_route_template_steps';

    protected $fillable = [
        'route_template_id',
        'operation_master_id',
        'sequence_no',
        'is_mandatory',
        'qc_gate_required',
        'qc_gate_mode',
        'qc_gate_type',
        'qc_gate_remarks',
        'remarks',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'is_mandatory' => 'boolean',
        'qc_gate_required' => 'boolean',
    ];

    public function routeTemplate()
    {
        return $this->belongsTo(ProductionRouteTemplate::class, 'route_template_id');
    }

    public function operationMaster()
    {
        return $this->belongsTo(ProductionOperationMaster::class, 'operation_master_id');
    }
}
