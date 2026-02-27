<?php

namespace App\Models;

use App\Enums\BomItemMaterialCategory;
use App\Enums\BomItemMaterialSource;
use App\Enums\BomItemProcurementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'bom_id',
        'parent_item_id',
        'level',
        'item_code',
        'description',
        'assembly_type',
        'sequence_no',
        'drawing_number',
        'drawing_revision',
        'material_category',
        'item_id',
        'uom_id',
        'dimensions',
        'quantity',
        'unit_weight',
        'total_weight',
        'unit_area_m2',
        'total_area_m2',
        'unit_cut_length_m',
        'total_cut_length_m',
        'unit_weld_length_m',
        'total_weld_length_m',
        'scrap_percentage',
        'procurement_type',
        'material_source',
        'is_billable',
        'remarks',
        'grade',
    ];

    protected $casts = [
        'material_category' => BomItemMaterialCategory::class,
        'procurement_type' => BomItemProcurementType::class,
        'material_source' => BomItemMaterialSource::class,
        'is_billable' => 'boolean',
        'dimensions' => 'array',
        'unit_area_m2' => 'decimal:4',
        'total_area_m2' => 'decimal:4',
        'unit_cut_length_m' => 'decimal:4',
        'total_cut_length_m' => 'decimal:4',
        'unit_weld_length_m' => 'decimal:4',
        'total_weld_length_m' => 'decimal:4',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BomItem::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BomItem::class, 'parent_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function isAssembly(): bool
    {
        return $this->material_category === BomItemMaterialCategory::FABRICATED_ASSEMBLY;
    }

    public function isLeafMaterial(): bool
    {
        return ! $this->isAssembly();
    }

    

    /**
     * Multiplier from parent fabricated assemblies.
     *
     * If $itemsById is provided, we resolve parents from that map (avoids N+1 queries).
     *
     * @param array<int, \App\Models\BomItem>|null $itemsById
     */
    public function effectiveMultiplier(?array $itemsById = null): float
    {
        $mult = 1.0;

        $current = $this;
        while (! empty($current->parent_item_id)) {
            $parentId = (int) $current->parent_item_id;

            /** @var \App\Models\BomItem|null $parent */
            $parent = $itemsById ? ($itemsById[$parentId] ?? null) : $current->parent;

            if (! $parent) {
                break;
            }

            $q = (float) ($parent->quantity ?? 1);

            if ($q <= 0) {
                // If a parent qty is zero, effective requirement becomes zero.
                return 0.0;
            }

            $mult *= $q;
            $current = $parent;
        }

        return $mult;
    }

    /**
     * Billable flag with parent inheritance.
     *
     * If ANY ancestor item is marked NOT billable, this returns false.
     *
     * Use this when deciding dispatch/billing scope for nested BOM items.
     *
     * @param array<int, \App\Models\BomItem>|null $itemsById
     */
    public function effectiveBillable(?array $itemsById = null): bool
    {
        $current = $this;
        $visited = [];

        while ($current) {
            // Safety: prevent infinite loops in case of bad parent links.
            if (isset($visited[$current->id])) {
                break;
            }
            $visited[$current->id] = true;

            $selfBillable = (bool) ($current->is_billable ?? true);
            if (! $selfBillable) {
                return false;
            }

            if (empty($current->parent_item_id)) {
                break;
            }

            $parentId = (int) $current->parent_item_id;

            /** @var \App\Models\BomItem|null $parent */
            $parent = $itemsById ? ($itemsById[$parentId] ?? null) : $current->parent;
            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        return true;
    }

    /**
     * Effective quantity for this line = own quantity * product of parent assembly quantities.
     *
     * @param array<int, \App\Models\BomItem>|null $itemsById
     */
    public function effectiveQuantity(?array $itemsById = null): float
    {
        $qty = (float) ($this->quantity ?? 0);
        if ($qty <= 0) {
            return 0.0;
        }

        return $qty * $this->effectiveMultiplier($itemsById);
    }

    /**
     * Effective unit weight for this line.
     * Prefer explicit unit_weight; else derive from total_weight/quantity if possible.
     */
    public function effectiveUnitWeight(): float
    {
        if ($this->unit_weight !== null && $this->unit_weight !== '') {
            return (float) $this->unit_weight;
        }

        $qty = (float) ($this->quantity ?? 0);
        $tot = (float) ($this->total_weight ?? 0);

        if ($qty > 0 && $tot > 0) {
            return $tot / $qty;
        }

        return 0.0;
    }

    /**
     * Effective total weight for this line = effectiveQuantity * effectiveUnitWeight.
     *
     * @param array<int, \App\Models\BomItem>|null $itemsById
     */
    public function effectiveTotalWeight(?array $itemsById = null): float
    {
        $unit = $this->effectiveUnitWeight();
        if ($unit <= 0) {
            return 0.0;
        }

        return $unit * $this->effectiveQuantity($itemsById);
    }
public function scopeAssemblies($query)
    {
        return $query->where('material_category', BomItemMaterialCategory::FABRICATED_ASSEMBLY->value);
    }

    public function scopeLeafMaterials($query)
    {
        return $query->where('material_category', '!=', BomItemMaterialCategory::FABRICATED_ASSEMBLY->value);
    }

    public function getIndentedDescriptionAttribute(): string
    {
        $indent = str_repeat('— ', (int) $this->level);
        return $indent . ($this->description ?? '');
    }

    public function getGradeAttribute(): ?string
    {
        // Prefer BOM item override (if stored), otherwise fall back to Item master
        $local = $this->getAttributeFromArray('grade');
        return $local !== null && $local !== '' ? $local : ($this->item?->grade);
    }

    public function getFormattedDimensionsAttribute(): ?string
    {
        $dims = $this->dimensions;

        if (! is_array($dims) || empty($dims)) {
            return null;
        }

        // Plate-style: T x W x L (mm)
        if (isset($dims['thickness_mm'], $dims['width_mm'], $dims['length_mm'])) {
            return sprintf(
                '%s x %s x %s mm',
                $dims['thickness_mm'],
                $dims['width_mm'],
                $dims['length_mm']
            );
        }

        // Section-style: Section x L (mm)
        if (isset($dims['section'], $dims['length_mm'])) {
            return sprintf(
                '%s x %s mm',
                $dims['section'],
                $dims['length_mm']
            );
        }

        // Assembly-style: span + depth (+ leaves)
        if (isset($dims['span_length_m'], $dims['depth_mm'])) {
            $parts = [
                'Span ' . $dims['span_length_m'] . ' m',
                'Depth ' . $dims['depth_mm'] . ' mm',
            ];

            if (isset($dims['leaves'])) {
                $parts[] = 'Leaves ' . $dims['leaves'];
            }

            return implode(', ', $parts);
        }

        // Fallback: key: value list
        return collect($dims)
            ->map(fn ($v, $k) => "{$k}: {$v}")
            ->implode(', ');
    }
}
