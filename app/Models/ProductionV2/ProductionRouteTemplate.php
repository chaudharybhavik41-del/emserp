<?php

namespace App\Models\ProductionV2;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionRouteTemplate extends Model
{
    use HasFactory;

    protected $table = 'production_v2_route_templates';

    protected $fillable = [
        'project_id',
        'template_code',
        'template_name',
        'applies_to',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function steps()
    {
        return $this->hasMany(ProductionRouteTemplateStep::class, 'route_template_id')
            ->orderBy('sequence_no')
            ->orderBy('id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
