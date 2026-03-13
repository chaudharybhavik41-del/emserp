<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionQcGateEvent extends Model
{
    use HasFactory;

    protected $table = 'production_v2_qc_gate_events';

    protected $fillable = [
        'project_id',
        'operation_master_id',
        'part_route_step_id',
        'assembly_route_step_id',
        'part_definition_id',
        'assembly_id',
        'related_dpr_id',
        'gate_date',
        'gate_mode',
        'gate_type',
        'result',
        'checked_by',
        'inspector_agency',
        'reference_no',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gate_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function operationMaster()
    {
        return $this->belongsTo(ProductionOperationMaster::class, 'operation_master_id');
    }

    public function partRouteStep()
    {
        return $this->belongsTo(ProductionPartRouteStep::class, 'part_route_step_id');
    }

    public function assemblyRouteStep()
    {
        return $this->belongsTo(ProductionAssemblyRouteStep::class, 'assembly_route_step_id');
    }

    public function partDefinition()
    {
        return $this->belongsTo(ProductionPartDefinition::class, 'part_definition_id');
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function relatedDpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'related_dpr_id');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
