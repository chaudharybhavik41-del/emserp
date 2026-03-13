<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionAssemblyRouteStep extends Model
{
    use HasFactory;

    protected $table = 'production_v2_assembly_route_steps';

    protected $fillable = [
        'assembly_id',
        'route_template_id',
        'route_template_step_id',
        'operation_master_id',
        'operation_code',
        'operation_name',
        'entry_mode',
        'entry_route',
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

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function operationMaster()
    {
        return $this->belongsTo(ProductionOperationMaster::class, 'operation_master_id');
    }

    public function qcGateEvents()
    {
        return $this->hasMany(ProductionQcGateEvent::class, 'assembly_route_step_id');
    }
}
