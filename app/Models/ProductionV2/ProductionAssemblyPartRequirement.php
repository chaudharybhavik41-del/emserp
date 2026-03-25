<?php

namespace App\Models\ProductionV2;

use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionAssemblyPartRequirement extends Model
{
    use HasFactory;

    protected $table = 'production_v2_assembly_part_requirements';

    protected $fillable = [
        'assembly_id',
        'part_definition_id',
        'required_qty',
        'uom_id',
        'consumption_sequence',
        'is_mandatory',
        'is_client_dispatchable',
        'remarks',
    ];

    protected $casts = [
        'required_qty' => 'decimal:3',
        'consumption_sequence' => 'integer',
        'is_mandatory' => 'boolean',
        'is_client_dispatchable' => 'boolean',
    ];

    public function assembly()
    {
        return $this->belongsTo(ProductionAssembly::class, 'assembly_id');
    }

    public function partDefinition()
    {
        return $this->belongsTo(ProductionPartDefinition::class, 'part_definition_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }
}
