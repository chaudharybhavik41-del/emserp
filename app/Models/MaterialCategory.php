<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_type_id',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class, 'material_type_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(MaterialSubcategory::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'material_category_id');
    }
}
