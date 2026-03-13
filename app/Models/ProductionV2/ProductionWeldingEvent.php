<?php

namespace App\Models\ProductionV2;

use App\Models\Item;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionWeldingEvent extends Model
{
    use HasFactory;

    protected $table = 'production_v2_welding_events';

    protected $fillable = [
        'project_id',
        'assembly_id',
        'dpr_id',
        'welding_process',
        'weld_date',
        'welder_id',
        'contractor_party_id',
        'joint_description',
        'line_no',
        'weld_size_mm',
        'wpss_ref',
        'consumable_item_id',
        'consumable_batch',
        'shielding_gas',
        'current_amp',
        'voltage',
        'travel_speed',
        'heat_input',
        'machine_id',
        'supervisor_id',
        'inspector_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'weld_date' => 'date',
        'weld_size_mm' => 'decimal:3',
        'current_amp' => 'decimal:2',
        'voltage' => 'decimal:2',
        'travel_speed' => 'decimal:3',
        'heat_input' => 'decimal:3',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function welder()
    {
        return $this->belongsTo(User::class, 'welder_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function consumableItem()
    {
        return $this->belongsTo(Item::class, 'consumable_item_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function dpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'dpr_id');
    }

    public function inspections()
    {
        return $this->hasMany(ProductionInspectionEvent::class, 'related_welding_event_id');
    }
}
