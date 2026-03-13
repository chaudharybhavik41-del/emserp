<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionInspectionEvent extends Model
{
    use HasFactory;

    protected $table = 'production_v2_inspection_events';

    protected $fillable = [
        'project_id',
        'assembly_id',
        'inspection_type',
        'inspection_date',
        'result',
        'related_dpr_id',
        'related_welding_event_id',
        'line_no',
        'defect_type',
        'defect_description',
        'repair_action',
        'reoffer_no',
        'retest_result',
        'checked_by',
        'inspector_agency',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'inspection_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function weldingEvent()
    {
        return $this->belongsTo(ProductionWeldingEvent::class, 'related_welding_event_id');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function relatedDpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'related_dpr_id');
    }

    public function reworkEvents()
    {
        return $this->hasMany(ProductionReworkEvent::class, 'source_inspection_event_id');
    }
}
