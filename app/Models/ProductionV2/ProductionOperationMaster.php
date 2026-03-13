<?php

namespace App\Models\ProductionV2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOperationMaster extends Model
{
    use HasFactory;

    protected $table = 'production_v2_operation_masters';

    protected $fillable = [
        'code',
        'name',
        'applies_to',
        'entry_mode',
        'entry_route',
        'requires_machine',
        'requires_qc',
        'is_system',
        'is_active',
        'sort_order',
        'remarks',
    ];

    protected $casts = [
        'requires_machine' => 'boolean',
        'requires_qc' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function routeTemplateSteps()
    {
        return $this->hasMany(ProductionRouteTemplateStep::class, 'operation_master_id');
    }

    public function qcGateEvents()
    {
        return $this->hasMany(ProductionQcGateEvent::class, 'operation_master_id');
    }
}
