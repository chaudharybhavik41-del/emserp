<?php

namespace App\Models\Production;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionAssembly extends Model
{
    use HasFactory;

    protected $table = 'production_assemblies';

    protected $fillable = [
        'project_id',
        'production_plan_id',
        'production_plan_item_id',
        'production_dpr_line_id',
        'assembly_mark',
        'assembly_type',
        'weight_kg',
        'status',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
    ];

    public function components()
    {
        return $this->hasMany(ProductionAssemblyComponent::class, 'production_assembly_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
