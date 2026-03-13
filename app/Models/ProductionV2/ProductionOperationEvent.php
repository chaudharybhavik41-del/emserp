<?php

namespace App\Models\ProductionV2;

use App\Models\Machine;
use App\Models\Party;
use App\Models\Production\ProductionDpr;
use App\Models\Project;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOperationEvent extends Model
{
    use HasFactory;

    protected $table = 'production_v2_operation_events';

    protected $fillable = [
        'project_id',
        'operation_master_id',
        'part_route_step_id',
        'assembly_route_step_id',
        'part_definition_id',
        'assembly_id',
        'wip_item_id',
        'dpr_id',
        'operation_date',
        'shift',
        'qty',
        'uom_id',
        'machine_id',
        'worker_user_id',
        'contractor_party_id',
        'result',
        'reference_no',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'qty' => 'decimal:3',
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

    public function wipItem()
    {
        return $this->belongsTo(ProductionWipItem::class, 'wip_item_id');
    }

    public function dpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'dpr_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }
}
