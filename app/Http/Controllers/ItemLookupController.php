<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemLookupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Item lookup for AJAX autocompletes (Select2 style).
     *
     * Query params:
     *  - q:   free-text search on code / name / short_name
     *  - ids: comma-separated list of ids to fetch directly
     *  - limit: max rows (default 20, max 50)
     *  - non_raw_only: when truthy, exclude RAW material type (plates/sections etc.)
     */
    public function index(Request $request): JsonResponse
    {
        $ids = $request->input('ids');
        $q   = trim((string) $request->input('q', ''));

        $query = Item::query()
            ->with(['uom', 'type'])
            ->where('is_active', true);

        /**
         * 🔹 IMPORTANT:
         * For store requisitions we only want NON-RAW materials.
         * Raw items are those whose material type has code = 'RAW'.
         * So when non_raw_only=1 is passed, we exclude type.code = 'RAW'.
         */
        if ($request->boolean('non_raw_only')) {
            $query->whereHas('type', function ($q) {
                $q->where('code', '!=', 'RAW');
            });
        }

        // Explicit ids (preload selected values)
        if (! empty($ids)) {
            if (! is_array($ids)) {
                $ids = explode(',', (string) $ids);
            }

            $ids = array_values(array_filter(array_map('intval', $ids)));

            if (empty($ids)) {
                return response()->json(['results' => []]);
            }

            $query->whereIn('id', $ids);
        }
        // Text search
        elseif ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('code', 'like', '%' . $q . '%')
                    ->orWhere('name', 'like', '%' . $q . '%')
                    ->orWhere('short_name', 'like', '%' . $q . '%');
            });
        }

        // Limit the result size
        $limit = (int) $request->input('limit', 20);
        if ($limit < 5) {
            $limit = 5;
        } elseif ($limit > 50) {
            $limit = 50;
        }

        $items = $query
            ->orderBy('code')
            ->limit($limit)
            ->get();

        $results = $items->map(function (Item $item) {
            $label = trim(($item->code ? $item->code . ' - ' : '') . $item->name);
            if (! empty($item->grade)) {
                $label .= ' (' . $item->grade . ')';
            }

            return [
                'id'       => $item->id,
                'text'     => $label,
                'code'     => $item->code,
                'name'     => $item->name,
                'grade'    => $item->grade,
                'uom_id'   => $item->uom_id,
                'uom_name' => optional($item->uom)->name,
            ];
        })->values();

        return response()->json([
            'results' => $results,
        ]);
    }
}
