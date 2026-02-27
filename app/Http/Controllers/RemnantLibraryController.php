<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use App\Models\StoreStockItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RemnantLibraryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:store.stock.view');
    }

    public function index(Request $request): View
    {
        $items    = Item::orderBy('name')->get();
        $projects = Project::orderBy('code')->get();

        $query = StoreStockItem::with(['item.uom', 'project'])
            ->where('is_remnant', true)
            ->orderByDesc('id');

        // Filters
        if ($request->filled('item_id')) {
            $query->where('item_id', $request->integer('item_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('material_category')) {
            $query->where('material_category', $request->input('material_category'));
        }

        if ($request->filled('grade')) {
            $query->where('grade', 'like', '%' . trim($request->input('grade')) . '%');
        }

        if ($request->filled('thickness_mm')) {
            $query->where('thickness_mm', $request->input('thickness_mm'));
        }

        if ($request->filled('is_client_material')) {
            // Expect "0" or "1"
            $query->where('is_client_material', (bool) $request->input('is_client_material'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($qi) use ($search) {
                    $qi->where('name', 'like', '%' . $search . '%')
                       ->orWhere('code', 'like', '%' . $search . '%');
                })
                ->orWhere('plate_number', 'like', '%' . $search . '%')
                ->orWhere('heat_number', 'like', '%' . $search . '%')
                ->orWhere('grade', 'like', '%' . $search . '%')
                ->orWhere('location', 'like', '%' . $search . '%');
            });
        }

        // Only show pieces with some available qty by default
        if (! $request->boolean('include_zero_available')) {
            $query->where('qty_pcs_available', '>', 0);
        }

        $remnants = $query->paginate(50)->withQueryString();

        // Distinct categories & statuses for filters
        $categories = StoreStockItem::where('is_remnant', true)
            ->select('material_category')
            ->whereNotNull('material_category')
            ->distinct()
            ->orderBy('material_category')
            ->pluck('material_category')
            ->toArray();

        $statuses = StoreStockItem::where('is_remnant', true)
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->toArray();

        // Distinct thickness list for quick filter (for plates)
        $thicknesses = StoreStockItem::where('is_remnant', true)
            ->whereNotNull('thickness_mm')
            ->select('thickness_mm')
            ->distinct()
            ->orderBy('thickness_mm')
            ->pluck('thickness_mm')
            ->toArray();

        return view('store_remnants.index', [
            'remnants'    => $remnants,
            'items'       => $items,
            'projects'    => $projects,
            'categories'  => $categories,
            'statuses'    => $statuses,
            'thicknesses' => $thicknesses,
            'filters'     => $request->all(),
        ]);
    }
}
