<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDesignRelease extends Model
{
    use HasFactory;

    protected $table = 'production_v2_design_releases';

    protected $fillable = [
        'project_id',
        'release_number',
        'release_date',
        'remarks',
        'released_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'release_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function parts()
    {
        return $this->hasMany(ProductionPartDefinition::class, 'design_release_id');
    }

    public function assemblies()
    {
        return $this->hasMany(ProductionAssembly::class, 'design_release_id');
    }

    public function cuttingPlans()
    {
        return $this->hasMany(ProductionCuttingPlan::class, 'design_release_id');
    }

    public function materialRequirements()
    {
        return $this->hasMany(ProductionMaterialRequirement::class, 'design_release_id');
    }
}
