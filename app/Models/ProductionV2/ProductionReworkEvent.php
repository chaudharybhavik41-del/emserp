<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\Production\ProductionDpr;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionReworkEvent extends Model
{
    use HasFactory;

    protected $table = 'production_v2_rework_events';

    protected $fillable = [
        'project_id',
        'assembly_id',
        'source_inspection_event_id',
        'rework_date',
        'reason_code',
        'reason_description',
        'action_taken',
        'rework_dpr_id',
        'reoffer_date',
        'final_result',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rework_date' => 'date',
        'reoffer_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function sourceInspection()
    {
        return $this->belongsTo(ProductionInspectionEvent::class, 'source_inspection_event_id');
    }

    public function reworkDpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'rework_dpr_id');
    }
}
