<?php

namespace App\Models\ProductionV2;

use App\Models\Item;
use App\Models\Project;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionPartDefinition extends Model
{
    use HasFactory;

    protected $table = 'production_v2_part_definitions';

    protected $fillable = [
        'project_id',
        'part_code',
        'part_name',
        'part_type',
        'uom_id',
        'required_qty',
        'description',
        'material_item_id',
        'material_grade',
        'material_category',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'unit_weight_kg',
        'unit_area_m2',
        'unit_cut_length_m',
        'unit_weld_length_m',
        'is_interchangeable',
        'is_cuttable',
        'is_section_item',
        'is_bought_out',
        'drawing_ref',
        'route_template_id',
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
        'required_qty' => 'decimal:3',
        'thickness_mm' => 'decimal:3',
        'width_mm' => 'decimal:3',
        'length_mm' => 'decimal:3',
        'unit_weight_kg' => 'decimal:3',
        'unit_area_m2' => 'decimal:4',
        'unit_cut_length_m' => 'decimal:4',
        'unit_weld_length_m' => 'decimal:4',
        'revision_no' => 'integer',
        'is_interchangeable' => 'boolean',
        'is_cuttable' => 'boolean',
        'is_section_item' => 'boolean',
        'is_bought_out' => 'boolean',
        'released_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function materialItem()
    {
        return $this->belongsTo(Item::class, 'material_item_id');
    }

    public function requirements()
    {
        return $this->hasMany(ProductionAssemblyPartRequirement::class, 'part_definition_id');
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
        return $this->hasMany(ProductionPartRouteStep::class, 'part_definition_id')
            ->orderBy('sequence_no')
            ->orderBy('id');
    }

    public function qcGateEvents()
    {
        return $this->hasMany(ProductionQcGateEvent::class, 'part_definition_id');
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

    public static function applyMaterialItemDefaults(array $attributes, ?Item $item): array
    {
        if (! $item) {
            return $attributes;
        }

        if (blank($attributes['material_grade'] ?? null) && filled($item->grade)) {
            $attributes['material_grade'] = $item->grade;
        }

        if (blank($attributes['uom_id'] ?? null) && ! empty($item->uom_id)) {
            $attributes['uom_id'] = (int) $item->uom_id;
        }

        $partType = strtolower(trim((string) ($attributes['part_type'] ?? '')));
        $materialCategory = strtolower(trim((string) ($attributes['material_category'] ?? '')));
        $shouldUseThickness = in_array($partType, ['cuttable_plate', 'section'], true)
            || in_array($materialCategory, ['steel_plate', 'steel_section'], true);

        if ($shouldUseThickness && static::floatOrNull($attributes['thickness_mm'] ?? null) === null && ! is_null($item->thickness)) {
            $attributes['thickness_mm'] = round((float) $item->thickness, 3);
        }

        $existingUnitWeight = static::floatOrNull($attributes['unit_weight_kg'] ?? null);
        if ($existingUnitWeight === null || $existingUnitWeight <= 0) {
            $calculatedUnitWeight = static::calculateUnitWeightKg($attributes, $item);
            if ($calculatedUnitWeight !== null && $calculatedUnitWeight > 0) {
                $attributes['unit_weight_kg'] = $calculatedUnitWeight;
            }
        }

        return $attributes;
    }

    public static function calculateUnitWeightKg(array $attributes, ?Item $item): ?float
    {
        $partType = strtolower(trim((string) ($attributes['part_type'] ?? '')));
        $materialCategory = strtolower(trim((string) ($attributes['material_category'] ?? '')));
        $thicknessMm = static::floatOrNull($attributes['thickness_mm'] ?? null);
        $widthMm = static::floatOrNull($attributes['width_mm'] ?? null);
        $lengthMm = static::floatOrNull($attributes['length_mm'] ?? null);

        if (
            in_array($partType, ['cuttable_plate'], true)
            || $materialCategory === 'steel_plate'
        ) {
            if ($thicknessMm && $widthMm && $lengthMm) {
                $density = $item && ! is_null($item->density) ? (float) $item->density : 7850.0;
                $volumeM3 = ($thicknessMm * $widthMm * $lengthMm) / 1_000_000_000.0;

                return round($density * $volumeM3, 3);
            }
        }

        if (
            in_array($partType, ['section'], true)
            || $materialCategory === 'steel_section'
            || ! empty($attributes['is_section_item'])
        ) {
            $weightPerMeter = $item && ! is_null($item->weight_per_meter) ? (float) $item->weight_per_meter : null;
            if ($weightPerMeter && $lengthMm) {
                return round($weightPerMeter * ($lengthMm / 1000.0), 3);
            }
        }

        return null;
    }

    protected static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
