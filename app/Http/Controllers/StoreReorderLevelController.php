<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseIndent;
use App\Models\PurchaseIndentItem;
use App\Models\StoreReorderLevel;
use App\Services\Store\LowStockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreReorderLevelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // View low-stock + reorder levels
        $this->middleware('permission:store.stock.view')->only(['index', 'lowStock']);

        // Maintain reorder levels
        $this->middleware('permission:store.stock_item.update')->only(['create', 'store', 'edit', 'update', 'destroy']);

        // Create indents from low-stock
        $this->middleware('permission:purchase.indent.create')->only(['createIndent']);
    }

    public function index(Request $request): View
    {
        $items    = Item::orderBy('name')->get();
        $projects = Project::orderBy('code')->get();

        $query = StoreReorderLevel::with(['item.uom', 'project', 'createdBy', 'updatedBy'])
            ->orderByDesc('id');

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->integer('item_id'));
        }

        if ($request->filled('project_id')) {
            $pid = $request->input('project_id');
            if ($pid === 'NULL' || $pid === '' || is_null($pid)) {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', (int) $pid);
            }
        }

        if ($request->filled('brand')) {
            $brand = trim((string) $request->input('brand'));
            if ($brand === '__ANY__') {
                $query->where(function ($q) {
                    $q->whereNull('brand')->orWhere('brand', '');
                });
            } else {
                $query->where('brand', $brand);
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        $levels = $query->paginate(25)->withQueryString();

        return view('store_reorder_levels.index', [
            'levels'   => $levels,
            'items'    => $items,
            'projects' => $projects,
            'filters'  => $request->all(),
        ]);
    }

    public function create(): View
    {
        $items    = Item::with('uom')->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('code')->get();

        $level = new StoreReorderLevel();
        $level->is_active = true;

        return view('store_reorder_levels.create', compact('items', 'projects', 'level'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id'     => ['required', 'integer', 'exists:items,id'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'project_id'  => ['nullable'],
            'min_qty'     => ['required', 'numeric', 'min:0'],
            'target_qty'  => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $brand = trim((string) ($data['brand'] ?? ''));
        if ($brand === '') {
            $brand = null;
        }

        $projectId = $data['project_id'] ?? null;
        if ($projectId === 'NULL' || $projectId === '' || is_null($projectId)) {
            $projectId = null;
        } else {
            $projectId = (int) $projectId;
        }

        $level = new StoreReorderLevel();
        $level->item_id    = (int) $data['item_id'];
        $level->brand      = $brand;
        $level->project_id = $projectId;
        $level->min_qty    = (float) $data['min_qty'];
        $level->target_qty = (float) $data['target_qty'];
        $level->is_active  = $request->boolean('is_active', true);
        $level->created_by = $request->user()?->id;
        $level->updated_by = $request->user()?->id;
        $level->save();

        return redirect()->route('store-reorder-levels.index')
            ->with('success', 'Reorder level saved successfully.');
    }

    public function edit(StoreReorderLevel $storeReorderLevel): View
    {
        $items    = Item::with('uom')->where('is_active', true)->orderBy('name')->get();
        $projects = Project::orderBy('code')->get();

        return view('store_reorder_levels.edit', [
            'level'    => $storeReorderLevel,
            'items'    => $items,
            'projects' => $projects,
        ]);
    }

    public function update(Request $request, StoreReorderLevel $storeReorderLevel): RedirectResponse
    {
        $data = $request->validate([
            'item_id'     => ['required', 'integer', 'exists:items,id'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'project_id'  => ['nullable'],
            'min_qty'     => ['required', 'numeric', 'min:0'],
            'target_qty'  => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $brand = trim((string) ($data['brand'] ?? ''));
        if ($brand === '') {
            $brand = null;
        }

        $projectId = $data['project_id'] ?? null;
        if ($projectId === 'NULL' || $projectId === '' || is_null($projectId)) {
            $projectId = null;
        } else {
            $projectId = (int) $projectId;
        }

        $storeReorderLevel->item_id    = (int) $data['item_id'];
        $storeReorderLevel->brand      = $brand;
        $storeReorderLevel->project_id = $projectId;
        $storeReorderLevel->min_qty    = (float) $data['min_qty'];
        $storeReorderLevel->target_qty = (float) $data['target_qty'];
        $storeReorderLevel->is_active  = $request->boolean('is_active', true);
        $storeReorderLevel->updated_by = $request->user()?->id;
        $storeReorderLevel->save();

        return redirect()->route('store-reorder-levels.index')
            ->with('success', 'Reorder level updated successfully.');
    }

    public function destroy(StoreReorderLevel $storeReorderLevel): RedirectResponse
    {
        $storeReorderLevel->delete();

        return redirect()->route('store-reorder-levels.index')
            ->with('success', 'Reorder level deleted successfully.');
    }

    public function lowStock(Request $request, LowStockService $svc): View
    {
        $items       = Item::orderBy('name')->get();
        $projects    = Project::orderBy('code')->get();
        $departments = Department::orderBy('name')->get();

        $query = StoreReorderLevel::with(['item.uom', 'project'])
            ->where('is_active', true)
            ->orderBy('item_id');

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->integer('item_id'));
        }

        if ($request->filled('project_id')) {
            $pid = $request->input('project_id');
            if ($pid === 'NULL') {
                $query->whereNull('project_id');
            } else {
                $query->where('project_id', (int) $pid);
            }
        }

        $levels = $query->get();
        $rows = $svc->buildLowStockRows($levels);

        if ($request->boolean('only_low')) {
            $rows = array_values(array_filter($rows, fn ($r) => (bool) ($r['is_low'] ?? false)));
        }

        return view('store_low_stock.index', [
            'rows'        => $rows,
            'items'       => $items,
            'projects'    => $projects,
            'departments' => $departments,
            'filters'     => $request->all(),
        ]);
    }

    public function createIndent(Request $request, LowStockService $svc): RedirectResponse
    {
        $data = $request->validate([
            'department_id'    => ['required', 'integer', 'exists:departments,id'],
            'required_by_date' => ['required', 'date'],
            'remarks'          => ['nullable', 'string', 'max:2000'],
            'level_ids'        => ['required', 'array', 'min:1'],
            'level_ids.*'      => ['integer', 'exists:store_reorder_levels,id'],
        ]);

        $levels = StoreReorderLevel::with(['item', 'project'])
            ->whereIn('id', $data['level_ids'])
            ->where('is_active', true)
            ->get();

        if ($levels->isEmpty()) {
            return back()->with('error', 'No valid reorder levels selected.');
        }

        $availability = $svc->availabilityByLevel($levels);

        // Group by project_id (NULL and numeric separately)
        $groups = $levels->groupBy(function (StoreReorderLevel $l) {
            return $l->project_id ? (string) ((int) $l->project_id) : 'NULL';
        });

        $createdIndents = [];

        DB::beginTransaction();
        try {
            foreach ($groups as $projectKey => $groupLevels) {
                $projectId = $projectKey === 'NULL' ? null : (int) $projectKey;

                // Build indent lines
                $lineRows = [];

                foreach ($groupLevels as $level) {
                    $avail = (float) ($availability[(int) $level->id] ?? 0);
                    $minQty = (float) ($level->min_qty ?? 0);
                    $targetQty = (float) ($level->target_qty ?? 0);

                    $isLow = $avail + 0.0001 < $minQty;
                    if (! $isLow) {
                        continue;
                    }

                    $suggested = max(0.0, $targetQty - $avail);
                    if ($suggested <= 0.0001) {
                        continue;
                    }

                    $lineRows[] = [
                        'level'      => $level,
                        'available'  => $avail,
                        'suggested'  => $suggested,
                    ];
                }

                if (empty($lineRows)) {
                    continue;
                }

                $indent = new PurchaseIndent();
                $indent->code             = $this->generateIndentCode();
                $indent->project_id       = $projectId;
                $indent->department_id    = (int) $data['department_id'];
                $indent->created_by       = $request->user()?->id;
                $indent->approved_by      = null;
                $indent->required_by_date = $data['required_by_date'];
                $indent->status           = 'draft';

                $baseRemark = 'Auto Low Stock indent.';
                if (! empty($data['remarks'])) {
                    $baseRemark .= ' ' . trim((string) $data['remarks']);
                }
                $indent->remarks = $baseRemark;
                $indent->save();

                $lineNo = 1;

                foreach ($lineRows as $row) {
                    /** @var StoreReorderLevel $level */
                    $level = $row['level'];

                    $item = $level->item;
                    if (! $item) {
                        throw ValidationException::withMessages([
                            'level_ids' => ['Invalid item on reorder level #' . $level->id],
                        ]);
                    }

                    $piItem = new PurchaseIndentItem();
                    $piItem->purchase_indent_id  = $indent->id;
                    $piItem->line_no             = $lineNo++;
                    $piItem->origin_type         = 'MINMAX';
                    $piItem->origin_id           = (int) $level->id;
                    $piItem->item_id             = (int) $item->id;

                    $piItem->brand               = $level->brand ? trim((string) $level->brand) : null;

                    // Low stock indents are generally for store items; no geometry
                    $piItem->length_mm           = null;
                    $piItem->width_mm            = null;
                    $piItem->thickness_mm        = null;

                    $piItem->density_kg_per_m3   = $item->density ?? null;
                    $piItem->weight_per_meter_kg = $item->weight_per_meter ?? null;
                    $piItem->weight_per_piece_kg = null;

                    $piItem->qty_pcs             = null;
                    $piItem->order_qty           = (float) $row['suggested'];

                    $piItem->uom_id              = !empty($item->uom_id) ? (int) $item->uom_id : null;
                    $piItem->grade               = $item->grade ?? null;
                    $piItem->description         = $item->name;
                    $piItem->remarks             = sprintf(
                        'Low stock. Min: %.3f, Target: %.3f, Available: %.3f',
                        (float) ($level->min_qty ?? 0),
                        (float) ($level->target_qty ?? 0),
                        (float) $row['available'],
                    );

                    $piItem->save();
                }

                $createdIndents[] = $indent;
            }

            if (empty($createdIndents)) {
                DB::rollBack();
                return back()->with('error', 'No low-stock lines found for the selected levels.');
            }

            DB::commit();

            if (count($createdIndents) === 1) {
                return redirect()->route('purchase-indents.show', $createdIndents[0])
                    ->with('success', 'Purchase Indent created from low stock.');
            }

            $codes = implode(', ', array_map(fn ($i) => $i->code ?? ('#' . $i->id), $createdIndents));

            return redirect()->route('purchase-indents.index')
                ->with('success', 'Purchase Indents created from low stock: ' . $codes);

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create indent: ' . $e->getMessage());
        }
    }

    private function generateIndentCode(): string
    {
        $year = date('y');
        $prefix = "IND-{$year}-";

        $lastIndent = PurchaseIndent::where('code', 'LIKE', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $newNumber = $lastIndent ? ((int) substr((string) $lastIndent->code, -4) + 1) : 1;

        return $prefix . str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }
}
