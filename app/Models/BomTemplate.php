<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'template_code',
        'name',
        'structure_type',
        'description',
        'status',
        'total_weight',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BomTemplateItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function recalculateTotalWeight(): void
    {
        $sum = $this->items()
            ->whereNull('deleted_at')
            ->sum('total_weight');

        $this->total_weight = $sum ?? 0;
        $this->save();
    }

    public static function generateCode(?string $structureType = null): string
    {
        $type = $structureType ? strtoupper($structureType) : 'GEN';
        $type = preg_replace('/[^A-Z0-9]/', '', $type) ?: 'GEN';
        $type = substr($type, 0, 6);

        $year = now()->year;

        $prefix = sprintf('BT-%s-%d-', $type, $year);

        $last = static::where('template_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;

        if ($last && preg_match('/(\d{4})$/', $last->template_code, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        $seq = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return $prefix . $seq;
    }

    /**
     * Assembly-wise weight (sum of all descendant items' total_weight).
     *
     * @return array<int,float>
     */
    public function getAssemblyWeightsAttribute(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        if ($items->isEmpty()) {
            return [];
        }

        $byParent = [];
        foreach ($items as $item) {
            $byParent[$item->parent_item_id ?? 0][] = $item;
        }

        // Build a lookup map for parent traversal (avoid N+1).
        $itemsById = $items->keyBy('id')->all();

        // Effective billable (inherits from parents)
        $effBillableCache = [];
        $effBillable = function (BomTemplateItem $item) use (&$effBillable, &$effBillableCache, $itemsById): bool {
            if (isset($effBillableCache[$item->id])) {
                return $effBillableCache[$item->id];
            }

            $self = (bool) ($item->is_billable ?? true);
            if (! $self) {
                return $effBillableCache[$item->id] = false;
            }

            if (! empty($item->parent_item_id)) {
                $pid = (int) $item->parent_item_id;
                if (isset($itemsById[$pid])) {
                    return $effBillableCache[$item->id] = $effBillable($itemsById[$pid]);
                }
            }

            return $effBillableCache[$item->id] = true;
        };

        $cache = [];

        $compute = function (BomTemplateItem $item) use (&$compute, &$cache, $byParent, $effBillable): float {
            if (isset($cache[$item->id])) {
                return $cache[$item->id];
            }

            $children = $byParent[$item->id] ?? [];

            if (empty($children)) {
                $isBillable = $effBillable($item);
                return $cache[$item->id] = $isBillable
                    ? (float) ($item->total_weight ?? 0)
                    : 0.0;
            }

            $sum = 0.0;
            foreach ($children as $child) {
                $sum += $compute($child);
            }

            return $cache[$item->id] = $sum;
        };

        $map = [];
        foreach ($items as $item) {
            if ($item->isAssembly()) {
                $map[$item->id] = $compute($item);
            }
        }

        return $map;
    }

    /**
     * Category-wise summary for leaf materials.
     *
     * @return array<string,array{lines:int,total_weight:float}>
     */
    public function getCategorySummaryAttribute(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        $summary = [];

        foreach ($items as $item) {
            if ($item->isAssembly()) {
                continue;
            }

            $key = $item->material_category?->value ?? 'unknown';

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'lines' => 0,
                    'total_weight' => 0.0,
                ];
            }

            $summary[$key]['lines']++;
            $summary[$key]['total_weight'] += (float) ($item->total_weight ?? 0);
        }

        return $summary;
    }
}


