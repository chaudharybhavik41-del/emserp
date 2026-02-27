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

class BomTemplateItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'bom_template_items';

    protected $fillable = [
        'bom_template_id',
        'parent_item_id',
        'level',
        'sequence_no',
        'item_code',
        'description',
        'assembly_type',
        'drawing_number',
        'drawing_revision',
        'material_category',
        'item_id',
        'uom_id',
        'grade',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(BomTemplate::class, 'bom_template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BomTemplateItem::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BomTemplateItem::class, 'parent_item_id');
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

    public function getIndentedDescriptionAttribute(): string
    {
        $prefix = str_repeat('— ', (int) $this->level);

        return $prefix . ($this->description ?? '');
    }

    public function getFormattedDimensionsAttribute(): string
    {
        $d = $this->dimensions ?? [];

        if (empty($d)) {
            return '';
        }

        // Plate: thickness x width x length
        if (! empty($d['thickness_mm']) && ! empty($d['width_mm']) && ! empty($d['length_mm'])) {
            return sprintf(
                '%s x %s x %s mm',
                $d['thickness_mm'],
                $d['width_mm'],
                $d['length_mm']
            );
        }

        // Section with length
        if (! empty($d['section']) && ! empty($d['length_mm'])) {
            return sprintf('%s x %s mm', $d['section'], $d['length_mm']);
        }

        // Girder style
        if (! empty($d['span_length_m']) && ! empty($d['depth_mm'])) {
            return sprintf(
                'Span %s m, Depth %s mm, Leaves %s',
                $d['span_length_m'],
                $d['depth_mm'],
                $d['leaves'] ?? '-'
            );
        }

        return json_encode($d);
    }
}
