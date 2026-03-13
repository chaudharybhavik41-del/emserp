<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCuttingPlan extends Model
{
    use HasFactory;

    protected $table = 'production_v2_cutting_plans';

    protected $fillable = [
        'project_id',
        'plan_number',
        'plan_date',
        'grade',
        'thickness_mm',
        'source_mode',
        'status',
        'revision_no',
        'revision_root_id',
        'previous_revision_id',
        'superseded_by_revision_id',
        'design_release_id',
        'released_by',
        'released_at',
        'superseded_at',
        'remarks',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'thickness_mm' => 'decimal:3',
        'revision_no' => 'integer',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function allocations()
    {
        return $this->hasMany(ProductionCuttingPlanAllocation::class, 'cutting_plan_id');
    }

    public function designRelease()
    {
        return $this->belongsTo(ProductionDesignRelease::class, 'design_release_id');
    }

    public function previousRevision()
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }

    public function supersededByRevision()
    {
        return $this->belongsTo(self::class, 'superseded_by_revision_id');
    }

    public function revisions()
    {
        return $this->hasMany(self::class, 'revision_root_id', 'revision_root_id')
            ->orderBy('revision_no')
            ->orderBy('id');
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
