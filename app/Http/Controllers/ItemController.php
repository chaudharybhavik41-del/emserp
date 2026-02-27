<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\MaterialType;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;
use App\Models\Uom;
use App\Models\Accounting\Account;
use App\Models\StoreReorderLevel;
use Illuminate\Http\Request;
use App\Services\Accounting\GstRateRecorderService;
use App\Services\ItemCodeGenerator;

class ItemController extends Controller
{
    public function __construct(
        protected GstRateRecorderService $gstRateRecorder,
        protected ItemCodeGenerator $itemCodeGenerator
    ) {
        $this->middleware('auth');

        // FIX: align with seeded permission keys (core.item.*)
        $this->middleware('permission:core.item.view')->only(['index']);
        $this->middleware('permission:core.item.create')->only(['create', 'store']);
        $this->middleware('permission:core.item.update')->only(['edit', 'update']);
        $this->middleware('permission:core.item.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Item::with(['type', 'category', 'subcategory', 'uom', 'expenseAccount']);

        if ($search = trim($request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('short_name', 'like', '%' . $search . '%');
            });
        }

        if ($typeId = $request->get('material_type_id')) {
            $query->where('material_type_id', $typeId);
        }

        if ($catId = $request->get('material_category_id')) {
            $query->where('material_category_id', $catId);
        }

        if ($subcatId = $request->get('material_subcategory_id')) {
            $query->where('material_subcategory_id', $subcatId);
        }

        $items = $query
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        $types         = MaterialType::orderBy('code')->get();
        $categories    = MaterialCategory::orderBy('code')->get();
        $subcategories = MaterialSubcategory::orderBy('code')->get();

        return view('items.index', compact('items', 'types', 'categories', 'subcategories'));
    }

    public function create()
    {
        $item = new Item();

        $types         = MaterialType::orderBy('code')->get();
        $categories    = MaterialCategory::with('type')->orderBy('code')->get();
        $subcategories = MaterialSubcategory::with('category.type')->orderBy('code')->get();
        $uoms          = Uom::orderBy('code')->get();
        $accounts      = Account::orderBy('name')->get();

        $brandTags = $this->brandTags();

        $defaultReorder = null;

        return view('items.create', compact(
            'item',
            'types',
            'categories',
            'subcategories',
            'uoms',
            'accounts',
            'brandTags',
            'defaultReorder'
        ));
    }

    public function store(StoreItemRequest $request)
    {
        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        // Auto-generate item code from category + subcategory prefix
        $category    = MaterialCategory::findOrFail($data['material_category_id']);
        $subcategory = MaterialSubcategory::find($data['material_subcategory_id']);
        $data['code'] = $this->itemCodeGenerator->generate($category, $subcategory);

        // GST values from form
        $gstRatePercent   = isset($data['gst_rate_percent']) ? (float) $data['gst_rate_percent'] : null;
        $gstEffectiveFrom = $request->input('gst_effective_from');

        // Normalize brands (trim + remove blanks + unique)
        $data['brands'] = $this->normalizeBrands($data['brands'] ?? null);

        $item = Item::create($data);

        $this->gstRateRecorder->syncForItem($item, $gstRatePercent, $gstEffectiveFrom);

        $this->syncDefaultReorderLevel($item, $request);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item created successfully.');
    }

    public function edit(Item $item)
    {
        $types         = MaterialType::orderBy('code')->get();
        $categories    = MaterialCategory::with('type')->orderBy('code')->get();
        $subcategories = MaterialSubcategory::with('category.type')->orderBy('code')->get();
        $uoms          = Uom::orderBy('code')->get();
        $accounts      = Account::orderBy('name')->get();

        $brandTags = $this->brandTags();

        $defaultReorder = StoreReorderLevel::query()
            ->where('item_id', $item->id)
            ->whereNull('brand')
            ->whereNull('project_id')
            ->first();

        return view('items.edit', compact(
            'item',
            'types',
            'categories',
            'subcategories',
            'uoms',
            'accounts',
            'brandTags',
            'defaultReorder'
        ));
    }

    public function update(UpdateItemRequest $request, Item $item)
    {
        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        // Never allow code change via UI
        $data['code'] = $item->code;

        // Normalize brands (trim + remove blanks + unique)
        $data['brands'] = $this->normalizeBrands($data['brands'] ?? null);

        $item->update($data);

        $gstRatePercent   = isset($data['gst_rate_percent']) ? (float) $data['gst_rate_percent'] : null;
        $gstEffectiveFrom = $request->input('gst_effective_from');

        $this->gstRateRecorder->syncForItem($item, $gstRatePercent, $gstEffectiveFrom);

        $this->syncDefaultReorderLevel($item, $request);

        return redirect()
            ->route('items.index')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()
            ->route('items.index')
            ->with('success', 'Item deleted successfully.');
    }

    /**
     * Build a tag list from all items' brands (supports casted array OR legacy json string).
     */

    /**
     * Sync default reorder level (brand=NULL, project_id=NULL) from Item form.
     * Applies to ALL brands combined (Option A).
     *
     * If the reorder fields are not present in the request (backward compatibility),
     * this method does nothing.
     */
    protected function syncDefaultReorderLevel(Item $item, Request $request): void
    {
        if (! $request->has('reorder_min_qty') && ! $request->has('reorder_target_qty')) {
            return;
        }

        $min    = (float) ($request->input('reorder_min_qty') ?? 0);
        $target = (float) ($request->input('reorder_target_qty') ?? 0);

        if ($min < 0) {
            $min = 0;
        }
        if ($target < 0) {
            $target = 0;
        }

        $level = StoreReorderLevel::query()->firstOrNew([
            'item_id'    => $item->id,
            'brand'      => null,
            'project_id' => null,
        ]);

        if (! $level->exists) {
            $level->created_by = $request->user()?->id;
        }

        $level->min_qty    = $min;
        $level->target_qty = $target;
        $level->is_active  = ($min > 0 || $target > 0);
        $level->updated_by = $request->user()?->id;

        $level->save();
    }

    protected function brandTags()
    {
        return Item::query()
            ->whereNotNull('brands')
            ->get(['brands'])
            ->pluck('brands')
            ->flatMap(function ($value) {
                // If casted -> array
                if (is_array($value)) {
                    return $value;
                }

                // If stored as JSON string
                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    // Fallback: comma-separated string
                    return array_map('trim', explode(',', $value));
                }

                return [];
            })
            ->filter(function ($b) {
                return is_string($b) && trim($b) !== '';
            })
            ->map(fn ($b) => trim((string) $b))
            ->unique()
            ->values();
    }

    /**
     * Normalize input brands into a clean array.
     */
    protected function normalizeBrands($brands): ?array
    {
        if (!is_array($brands)) {
            return null;
        }

        $clean = collect($brands)
            ->map(fn ($b) => trim((string) $b))
            ->filter(fn ($b) => $b !== '')
            ->unique()
            ->values()
            ->all();

        return empty($clean) ? null : $clean;
    }
}



