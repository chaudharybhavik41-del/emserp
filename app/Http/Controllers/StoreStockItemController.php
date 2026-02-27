<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use App\Models\StoreStockItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreStockItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:store.stock_item.view')
            ->only(['index', 'show']);

        $this->middleware('permission:store.stock_item.update')
            ->only(['edit', 'update']);
    }

   public function index(Request $request)
{
    $query = StoreStockItem::with(['item', 'project', 'receiptLine.receipt']);

    if ($request->filled('project_id')) {
        $query->where('project_id', $request->integer('project_id'));
    }

    if ($request->filled('item_id')) {
        $query->where('item_id', $request->integer('item_id'));
    }

    if ($request->filled('material_category')) {
        $query->where('material_category', $request->material_category);
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('is_client_material')) {
        $query->where('is_client_material', $request->is_client_material);
    }

    if ($request->filled('grade')) {
        $query->where('grade', 'like', '%' . $request->grade . '%');
    }

    $stockItems = $query->orderByDesc('id')->paginate(25);

    // AJAX request
    if ($request->ajax()) {
        return view('store.store_stock_items.partials.table', compact('stockItems'))->render();
    }

    $projects = Project::orderBy('code')->get();
    $items    = Item::orderBy('name')->get();

    $materialCategories = [
        'steel_plate'   => 'Steel Plate',
        'steel_section' => 'Steel Section',
        'consumable'    => 'Consumable',
        'bought_out'    => 'Bought-out Item',
    ];

    $statuses = [
        'available'  => 'Available',
        'reserved'   => 'Reserved',
        'consumed'   => 'Consumed',
        'scrap'      => 'Scrap',
        'blocked_qc' => 'Blocked (QC)',
    ];

    return view('store.store_stock_items.index', compact(
        'stockItems',
        'projects',
        'items',
        'materialCategories',
        'statuses'
    ));
}

    public function show(StoreStockItem $storeStockItem): View
    {
        $storeStockItem->load(['item', 'project', 'receiptLine.receipt']);

        return view('store.store_stock_items.show', [
            'stockItem' => $storeStockItem,
        ]);
    }

    public function edit(StoreStockItem $storeStockItem): View
    {
        $storeStockItem->load(['item', 'project', 'receiptLine.receipt']);

        return view('store.store_stock_items.edit', [
            'stockItem' => $storeStockItem,
        ]);
    }

    public function update(Request $request, StoreStockItem $storeStockItem): RedirectResponse
    {
        $data = $request->validate([
            'plate_number' => ['nullable', 'string', 'max:50'],
            'heat_number'  => ['nullable', 'string', 'max:100'],
            'mtc_number'   => ['nullable', 'string', 'max:100'],
            'location'     => ['nullable', 'string', 'max:100'],
            'remarks'      => ['nullable', 'string'],
        ]);

        $storeStockItem->update($data);

        return redirect()
            ->route('store-stock-items.index')
            ->with('success', 'Stock item updated successfully.');
    }
}
