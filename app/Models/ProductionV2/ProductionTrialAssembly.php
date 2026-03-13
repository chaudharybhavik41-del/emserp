<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionTrialAssembly extends Model
{
    use HasFactory;

    protected $table = 'production_v2_trial_assemblies';

    protected $fillable = [
        'project_id',
        'assembly_group_ref',
        'trial_date',
        'dpr_id',
        'checked_by',
        'inspector_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'trial_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function dpr()
    {
        return $this->belongsTo(ProductionDpr::class, 'dpr_id');
    }

    public function measurements()
    {
        return $this->hasMany(ProductionTrialAssemblyMeasurement::class, 'trial_assembly_id');
    }

    public function assemblies()
    {
        return $this->belongsToMany(
            ProductionAssembly::class,
            'production_v2_trial_assembly_links',
            'trial_assembly_id',
            'assembly_id'
        )->withPivot('sequence_no')->withTimestamps()->orderByPivot('sequence_no')->orderBy('assembly_code');
    }
}
