<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialSubcategory extends Model
{
    protected $fillable = [
        'material_category_id',
        'code',
        'item_code_prefix',   // <— NEW
        'name',
        'description',
        'expense_account_id',
        'asset_account_id',
        'inventory_account_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Accounting\Account::class, 'expense_account_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Accounting\Account::class, 'asset_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Accounting\Account::class, 'inventory_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'material_subcategory_id');
    }

    /**
     * Normalised prefix for item codes:
     * - Prefer explicit item_code_prefix
     * - Fallback to subcategory code
     * - Uppercase, alnum only, max 5 chars
     */
    public function getItemCodePrefix(): ?string
    {
        $source = $this->item_code_prefix ?: $this->code;

        if (! $source) {
            return null;
        }

        $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($source));

        return $prefix !== '' ? mb_substr($prefix, 0, 5) : null;
    }
}
