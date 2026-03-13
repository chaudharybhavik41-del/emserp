<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPartRouteStep extends Model
{
    use HasFactory;

    protected $table = 'production_v2_part_route_steps';

    protected $fillable = [
        'part_definition_id',
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

    public function partDefinition()
    {
        return $this->belongsTo(ProductionPartDefinition::class, 'part_definition_id');
    }

    public function operationMaster()
    {
        return $this->belongsTo(ProductionOperationMaster::class, 'operation_master_id');
    }

    public function qcGateEvents()
    {
        return $this->hasMany(ProductionQcGateEvent::class, 'part_route_step_id');
    }
}
