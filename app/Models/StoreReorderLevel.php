<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReorderLevel extends Model
{
    protected $table = 'store_reorder_levels';

    protected $fillable = [
        'item_id',
        'brand',
        'project_id',
        'min_qty',
        'target_qty',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'min_qty'    => 'decimal:3',
        'target_qty' => 'decimal:3',
        'is_active'  => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function normalizedBrand(): ?string
    {
        $b = trim((string) ($this->brand ?? ''));
        return $b === '' ? null : mb_strtoupper($b);
    }
}
