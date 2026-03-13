<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionAssembly extends Model
{
    use HasFactory;

    protected $table = 'production_v2_assemblies';

    protected $fillable = [
        'project_id',
        'assembly_code',
        'assembly_name',
        'assembly_type',
        'span_no',
        'leaf_no',
        'segment_no',
        'girder_no',
        'drawing_ref',
        'route_template_id',
        'sequence_no',
        'planned_qty',
        'planned_weight_kg',
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
        'updated_by',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'planned_qty' => 'decimal:3',
        'planned_weight_kg' => 'decimal:3',
        'revision_no' => 'integer',
        'released_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requirements()
    {
        return $this->hasMany(ProductionAssemblyPartRequirement::class, 'assembly_id')
            ->orderBy('consumption_sequence')
            ->orderBy('id');
    }

    public function fitups()
    {
        return $this->hasMany(ProductionFitup::class, 'assembly_id');
    }

    public function designRelease()
    {
        return $this->belongsTo(ProductionDesignRelease::class, 'design_release_id');
    }

    public function routeTemplate()
    {
        return $this->belongsTo(ProductionRouteTemplate::class, 'route_template_id');
    }

    public function routeSteps()
    {
        return $this->hasMany(ProductionAssemblyRouteStep::class, 'assembly_id')
            ->orderBy('sequence_no')
            ->orderBy('id');
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

    public function weldingEvents()
    {
        return $this->hasMany(ProductionWeldingEvent::class, 'assembly_id');
    }

    public function inspectionEvents()
    {
        return $this->hasMany(ProductionInspectionEvent::class, 'assembly_id');
    }

    public function reworkEvents()
    {
        return $this->hasMany(ProductionReworkEvent::class, 'assembly_id');
    }

    public function trialAssemblies()
    {
        return $this->belongsToMany(
            ProductionTrialAssembly::class,
            'production_v2_trial_assembly_links',
            'assembly_id',
            'trial_assembly_id'
        )->withPivot('sequence_no')->withTimestamps();
    }

    public function qcGateEvents()
    {
        return $this->hasMany(ProductionQcGateEvent::class, 'assembly_id');
    }
}
