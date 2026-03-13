<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionMaterialRequirement extends Model
{
    use HasFactory;

    protected $table = 'production_v2_material_requirements';

    protected $fillable = [
        'project_id',
        'requirement_number',
        'requirement_date',
        'basis',
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
        'requirement_date' => 'date',
        'revision_no' => 'integer',
        'released_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
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

    public function items()
    {
        return $this->hasMany(ProductionMaterialRequirementItem::class, 'material_requirement_id');
    }
}
