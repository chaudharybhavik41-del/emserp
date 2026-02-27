<?php

namespace App\Models;

use App\Enums\BomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tasks\Task as BomTask;

class Bom extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'bom_number',
        'version',
        'status',
        'total_weight',
        'finalized_date',
        'finalized_by',
        'metadata',
    ];

    protected $casts = [
        'status' => BomStatus::class,
        'metadata' => 'array',
        'finalized_date' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(BomTask::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === BomStatus::DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->status === BomStatus::FINALIZED;
    }

    public function isActive(): bool
    {
        return $this->status === BomStatus::ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === BomStatus::CLOSED;
    }

    public function recalculateTotalWeight(): void
    {
        // IMPORTANT:
        // BOM total should represent material consumption.
        // Do NOT double-count fabricated assemblies if they have children.
        $sum = $this->items()
            ->whereNull('deleted_at')
            ->where('material_category', '!=', \App\Enums\BomItemMaterialCategory::FABRICATED_ASSEMBLY->value)
            ->sum('total_weight');

        $this->total_weight = $sum ?? 0;
        $this->save();
    }


    public static function generateNumberForProject(Project $project): string
    {
        $year = now()->year;

        $lastBom = static::where('project_id', $project->id)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;

        if ($lastBom && preg_match('/(\d{4})$/', $lastBom->bom_number, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        $seq = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return sprintf('BOM-%s-%d-%s', $project->code, $year, $seq);
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

        // Build a quick lookup map for parent traversal (avoid N+1).
        $itemsById = $items->keyBy('id')->all();

        // Effective billable (inherits from parents)
        $effBillableCache = [];
        $effBillable = function (\App\Models\BomItem $item) use (&$effBillable, &$effBillableCache, $itemsById): bool {
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

        $compute = function (BomItem $item) use (&$compute, &$cache, $byParent, $effBillable): float {
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

