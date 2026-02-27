<?php

namespace App\Models;

use App\Enums\BomItemMaterialCategory;
use App\Enums\MaterialStockPieceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialStockPiece extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'item_id',
        'material_category',
        'thickness_mm',
        'width_mm',
        'length_mm',
        'section_profile',
        'weight_kg',
        'plate_number',
        'heat_number',
        'mtc_number',
        'origin_project_id',
        'origin_bom_id',
        'mother_piece_id',
        'status',
        'reserved_for_project_id',
        'reserved_for_bom_id',
        'source_type',
        'source_reference',
        'location',
        'remarks',
    ];

    protected $casts = [
        'material_category' => BomItemMaterialCategory::class,
        'status'            => MaterialStockPieceStatus::class,
        'weight_kg'         => 'float',
        'thickness_mm'      => 'integer',
        'width_mm'          => 'integer',
        'length_mm'         => 'integer',
    ];

    // Relationships

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function originProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'origin_project_id');
    }

    public function originBom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'origin_bom_id');
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(MaterialStockPiece::class, 'mother_piece_id');
    }

    public function remnants(): HasMany
    {
        return $this->hasMany(MaterialStockPiece::class, 'mother_piece_id');
    }

    public function reservedForProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'reserved_for_project_id');
    }

    public function reservedForBom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'reserved_for_bom_id');
    }

    // Helpers

    public function isPlate(): bool
    {
        return $this->material_category === BomItemMaterialCategory::STEEL_PLATE;
    }

    public function isSection(): bool
    {
        return $this->material_category === BomItemMaterialCategory::STEEL_SECTION;
    }

    public function isRemnant(): bool
    {
        return ! is_null($this->mother_piece_id);
    }

    // Scopes

    public function scopeAvailable($query)
    {
        return $query->where('status', MaterialStockPieceStatus::AVAILABLE->value);
    }

    public function scopeForGradeAndThickness($query, string $grade, int $thicknessMm)
    {
        // Plates: match item grade + thickness + category
        return $query
            ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
            ->where('thickness_mm', $thicknessMm)
            ->whereHas('item', function ($q) use ($grade) {
                $q->where('grade', $grade);
            });
    }

    public function scopeForSectionProfile($query, string $grade, string $sectionProfile)
    {
        // Sections: match item grade + section profile
        return $query
            ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
            ->where('section_profile', $sectionProfile)
            ->whereHas('item', function ($q) use ($grade) {
                $q->where('grade', $grade);
            });
    }
}
