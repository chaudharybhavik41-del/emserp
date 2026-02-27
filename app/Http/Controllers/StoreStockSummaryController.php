<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use App\Models\StoreStockItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StoreStockSummaryController extends Controller
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

        // Base query for aggregation
        $query = StoreStockItem::query()
            ->leftJoin('items', 'store_stock_items.item_id', '=', 'items.id')
            ->leftJoin('projects', 'store_stock_items.project_id', '=', 'projects.id');

        // Filters (apply BEFORE groupBy)
        if ($request->filled('item_id')) {
            $query->where('store_stock_items.item_id', $request->integer('item_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('store_stock_items.project_id', $request->integer('project_id'));
        }

        if ($request->filled('material_category')) {
            $query->where('store_stock_items.material_category', $request->input('material_category'));
        }

        if ($request->filled('status')) {
            $query->where('store_stock_items.status', $request->input('status'));
        }

        if ($request->boolean('only_available')) {
            $query->where('store_stock_items.qty_pcs_available', '>', 0);
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', '%' . $search . '%')
                  ->orWhere('items.code', 'like', '%' . $search . '%')
                  ->orWhere('store_stock_items.grade', 'like', '%' . $search . '%')
                  ->orWhere('store_stock_items.plate_number', 'like', '%' . $search . '%')
                  ->orWhere('store_stock_items.heat_number', 'like', '%' . $search . '%');
            });
        }

        // Select aggregates
        $query->selectRaw('
            store_stock_items.item_id,
            store_stock_items.project_id,
            store_stock_items.material_category,
            store_stock_items.grade,
            items.code as item_code,
            items.name as item_name,
            projects.code as project_code,
            projects.name as project_name,
            SUM(COALESCE(store_stock_items.qty_pcs_total, 0)) as qty_pcs_total,
            SUM(COALESCE(store_stock_items.qty_pcs_available, 0)) as qty_pcs_available,
            SUM(COALESCE(store_stock_items.weight_kg_total, 0)) as weight_kg_total,
            SUM(COALESCE(store_stock_items.weight_kg_available, 0)) as weight_kg_available
        ');

        // Group by non-aggregated columns
        $query->groupBy([
            'store_stock_items.item_id',
            'store_stock_items.project_id',
            'store_stock_items.material_category',
            'store_stock_items.grade',
            'items.code',
            'items.name',
            'projects.code',
            'projects.name',
        ]);

        // Order: by item, then project
        $query->orderBy('items.name')
              ->orderBy('projects.code');

        $summaryRows = $query->paginate(50)->withQueryString();

        // For filter dropdowns
        $categories = StoreStockItem::select('material_category')
            ->whereNotNull('material_category')
            ->distinct()
            ->orderBy('material_category')
            ->pluck('material_category')
            ->toArray();

        $statuses = StoreStockItem::select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->toArray();

        return view('store_stock.summary', [
            'rows'      => $summaryRows,
            'items'     => $items,
            'projects'  => $projects,
            'categories'=> $categories,
            'statuses'  => $statuses,
            'filters'   => $request->all(),
        ]);
    }
}
