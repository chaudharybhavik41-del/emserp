<?php

namespace App\Services\Store;

use App\Models\StoreReorderLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LowStockService
{
    /**
     * Build a map of reorder_level_id => available_qty.
     *
     * Quantity is treated as a generic qty for store items:
     * - Prefer weight_kg_available (used as generic qty for non-raw)
     * - Fallback to qty_pcs_available when weight_kg_available is NULL
     *
     * Only OWN material is considered (is_client_material = 0)
     * and only AVAILABLE stock is considered.
     *
     * @param Collection<int, StoreReorderLevel> $levels
     * @return array<int, float>
     */
    public function availabilityByLevel(Collection $levels): array
    {
        if ($levels->isEmpty()) {
            return [];
        }

        // Collect relevant item ids
        $itemIds = $levels->pluck('item_id')->filter()->unique()->values()->all();

        // Aggregate stock by (item_id, brand_key, project_id)
        // brand_key: normalized uppercase trimmed; '__ANY__' for empty/null
        $rows = DB::table('store_stock_items')
            ->whereIn('item_id', $itemIds)
            ->where('is_client_material', 0)
            ->where('status', 'available')
            ->selectRaw("item_id, COALESCE(NULLIF(UPPER(TRIM(brand)), ''), '__ANY__') as brand_key, project_id")
            ->selectRaw("SUM(CASE WHEN weight_kg_available IS NOT NULL THEN weight_kg_available ELSE COALESCE(qty_pcs_available,0) END) as qty")
            ->groupBy('item_id', 'brand_key', 'project_id')
            ->get();

        // Map: item_id|brand_key|project_id_key => qty
        $stockMap = [];
        foreach ($rows as $r) {
            $itemId = (int) ($r->item_id ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $brandKey = (string) ($r->brand_key ?? '__ANY__');
            $projectKey = is_null($r->project_id) ? 'NULL' : (string) ((int) $r->project_id);
            $qty = (float) ($r->qty ?? 0);

            $stockMap[$itemId][$brandKey][$projectKey] = ($stockMap[$itemId][$brandKey][$projectKey] ?? 0) + $qty;
        }

        $out = [];

        foreach ($levels as $level) {
            $levelId = (int) $level->id;
            $itemId  = (int) $level->item_id;

            $minProjectId = $level->project_id ? (int) $level->project_id : null;

            $levelBrand = trim((string) ($level->brand ?? ''));
            $brandKey = $levelBrand === '' ? null : mb_strtoupper($levelBrand);

            $available = 0.0;

            // Project scoping:
            // - If reorder level is GENERAL (project_id NULL): consider only GENERAL stock (project_id NULL)
            // - If reorder level is project-specific: consider GENERAL stock + SAME project stock
            $projectKeys = [];
            if ($minProjectId === null) {
                $projectKeys = ['NULL'];
            } else {
                $projectKeys = ['NULL', (string) $minProjectId];
            }

            if (! isset($stockMap[$itemId])) {
                $out[$levelId] = 0.0;
                continue;
            }

            $itemStock = $stockMap[$itemId];

            if ($brandKey === null) {
                // ANY brand: sum across all brand keys
                foreach ($itemStock as $bKey => $projRows) {
                    foreach ($projectKeys as $pKey) {
                        $available += (float) ($projRows[$pKey] ?? 0);
                    }
                }
            } else {
                // Specific brand: match exact normalized brand
                // Note: stock rows with empty brand are stored as '__ANY__' and will not be counted.
                $projRows = $itemStock[$brandKey] ?? null;
                if ($projRows) {
                    foreach ($projectKeys as $pKey) {
                        $available += (float) ($projRows[$pKey] ?? 0);
                    }
                }
            }

            $out[$levelId] = $available;
        }

        return $out;
    }

    /**
     * Build low stock rows with computed values.
     *
     * @param Collection<int, StoreReorderLevel> $levels
     * @return array<int, array<string, mixed>>
     */
    public function buildLowStockRows(Collection $levels): array
    {
        $avail = $this->availabilityByLevel($levels);

        $rows = [];

        foreach ($levels as $level) {
            $available = (float) ($avail[(int) $level->id] ?? 0);
            $minQty    = (float) ($level->min_qty ?? 0);
            $targetQty = (float) ($level->target_qty ?? 0);

            $isLow = $available + 0.0001 < $minQty;
            $suggested = $isLow ? max(0.0, $targetQty - $available) : 0.0;

            $rows[] = [
                'level'          => $level,
                'available_qty'  => $available,
                'min_qty'        => $minQty,
                'target_qty'     => $targetQty,
                'is_low'         => $isLow,
                'suggested_qty'  => $suggested,
            ];
        }

        return $rows;
    }
}
