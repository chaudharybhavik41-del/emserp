<?php

namespace App\Http\Controllers;

use App\Models\FuelIssue;
use App\Models\Machine;
use App\Models\Project;
use App\Models\StoreStockItem;
use App\Services\Accounting\FuelIssuePostingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FuelIssueController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:store.issue.view')->only(['index', 'show']);
        $this->middleware('permission:store.issue.create')->only(['create', 'store']);
        $this->middleware('permission:store.issue.post_to_accounts')->only(['postToAccounts']);
    }

    public function index(): View
    {
        $issues = FuelIssue::with(['project', 'machine', 'item', 'uom', 'voucher'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(25);

        return view('fuel_issues.index', compact('issues'));
    }

    public function create(Request $request): View
    {
        $projects = Project::orderBy('code')->orderBy('name')->get();
        $machinesQuery = Machine::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->orderBy('name');

        if (Schema::hasColumn('machines', 'allow_fuel_issue')) {
            $machinesQuery->where('allow_fuel_issue', true);
        }

        $machines = $machinesQuery->get(['id', 'code', 'name', 'current_project_id']);

        $selectedProjectId = $request->integer('project_id') ?: null;
        $fuelTypeCodes = $this->fuelMaterialTypeCodes();

        $stockItemsQuery = StoreStockItem::with(['item.uom', 'item.type', 'project'])
            ->where('status', 'available')
            ->where(function ($q) {
                $q->where('qty_pcs_available', '>', 0)
                    ->orWhere('weight_kg_available', '>', 0);
            })
            ->whereHas('item.type', function ($q) use ($fuelTypeCodes) {
                $q->whereIn('code', $fuelTypeCodes);
            });

        if ($selectedProjectId) {
            $stockItemsQuery->where(function ($q) use ($selectedProjectId) {
                $q->where(function ($q2) use ($selectedProjectId) {
                    $q2->where('is_client_material', true)
                        ->where('project_id', $selectedProjectId);
                })->orWhere(function ($q2) use ($selectedProjectId) {
                    $q2->where('is_client_material', false)
                        ->where(function ($q3) use ($selectedProjectId) {
                            $q3->whereNull('project_id')
                                ->orWhere('project_id', $selectedProjectId);
                        });
                });
            });
        }

        $stockItems = $stockItemsQuery
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        return view('fuel_issues.create', compact('projects', 'machines', 'stockItems', 'selectedProjectId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'issue_date' => ['required', 'date'],
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'store_stock_item_id' => ['required', 'integer', 'exists:store_stock_items,id'],
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'opening_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'closing_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (
            isset($data['opening_meter_reading'], $data['closing_meter_reading'])
            && (float) $data['closing_meter_reading'] < (float) $data['opening_meter_reading']
        ) {
            return back()
                ->withInput()
                ->withErrors(['closing_meter_reading' => 'Closing reading must be greater than or equal to opening reading.']);
        }

        DB::beginTransaction();

        try {
            $machine = Machine::query()->lockForUpdate()->findOrFail((int) $data['machine_id']);
            if (Schema::hasColumn('machines', 'allow_fuel_issue') && ! (bool) ($machine->allow_fuel_issue ?? false)) {
                throw new \RuntimeException('Fuel issue is not enabled for selected machine.');
            }

            $stockItem = StoreStockItem::with('item.type')
                ->lockForUpdate()
                ->where('status', 'available')
                ->findOrFail((int) $data['store_stock_item_id']);

            $qty = (float) $data['qty'];
            $availableQty = (float) ($stockItem->weight_kg_available ?? 0);
            if ($qty > $availableQty + 0.0001) {
                throw new \RuntimeException(
                    "Fuel issue quantity {$qty} exceeds available stock {$availableQty} for stock item #{$stockItem->id}."
                );
            }

            $this->ensureFuelStockSelection($stockItem);

            $projectId = (int) ($data['project_id'] ?? 0) ?: null;
            if (! $projectId) {
                $projectId = (int) ($machine->current_project_id ?? 0) ?: null;
            }

            if ($projectId) {
                if ((bool) $stockItem->is_client_material) {
                    if ((int) $stockItem->project_id !== (int) $projectId) {
                        throw new \RuntimeException('Client fuel stock must belong to the same project as fuel issue.');
                    }
                } else {
                    if (! is_null($stockItem->project_id) && (int) $stockItem->project_id !== (int) $projectId) {
                        throw new \RuntimeException('Selected fuel stock item belongs to a different project.');
                    }
                }
            }

            $unitRate = $this->resolveUnitRateFromStock($stockItem);
            $amount = round($unitRate * $qty, 2);

            $fuelIssue = new FuelIssue();
            $fuelIssue->issue_date = $data['issue_date'];
            $fuelIssue->machine_id = $machine->id;
            $fuelIssue->project_id = $projectId;
            $fuelIssue->store_stock_item_id = $stockItem->id;
            $fuelIssue->item_id = (int) $stockItem->item_id;
            $fuelIssue->uom_id = $stockItem->item?->uom_id;
            $fuelIssue->qty = $qty;
            $fuelIssue->unit_rate = $unitRate;
            $fuelIssue->amount = $amount;
            $fuelIssue->opening_meter_reading = $data['opening_meter_reading'] ?? null;
            $fuelIssue->closing_meter_reading = $data['closing_meter_reading'] ?? null;
            $fuelIssue->status = 'posted';
            $fuelIssue->remarks = $data['remarks'] ?? null;
            $fuelIssue->created_by = Auth::id();
            $fuelIssue->save();

            $fuelIssue->issue_number = app(\App\Services\DocumentNumberService::class)->fuelIssue($fuelIssue);
            $fuelIssue->save();

            $stockItem->weight_kg_available = max(0, $availableQty - $qty);
            if ($stockItem->weight_kg_available <= 0.0001) {
                $stockItem->weight_kg_available = 0;
                $stockItem->qty_pcs_available = 0;
                $stockItem->status = 'issued';
            }
            $stockItem->save();

            if ((bool) ($stockItem->is_client_material ?? false)) {
                $fuelIssue->accounting_status = 'not_required';
                $fuelIssue->accounting_posted_by = Auth::id();
                $fuelIssue->accounting_posted_at = now();
                $fuelIssue->save();
            }

            DB::commit();

            return redirect()
                ->route('fuel-issues.show', $fuelIssue)
                ->with('success', 'Fuel issue created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to save fuel issue: ' . $e->getMessage()]);
        }
    }

    public function show(FuelIssue $fuelIssue): View
    {
        $fuelIssue->load(['project', 'machine', 'item', 'uom', 'stockItem.project', 'voucher']);

        return view('fuel_issues.show', [
            'issue' => $fuelIssue,
        ]);
    }

    public function postToAccounts(FuelIssue $fuelIssue, FuelIssuePostingService $postingService): RedirectResponse
    {
        try {
            $voucher = $postingService->post($fuelIssue);

            if ($voucher) {
                return redirect()
                    ->route('fuel-issues.show', $fuelIssue)
                    ->with('success', 'Fuel issue posted to accounts as voucher ' . $voucher->voucher_no . '.');
            }

            return redirect()
                ->route('fuel-issues.show', $fuelIssue)
                ->with('success', 'No accounting entry required for this fuel issue.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('fuel-issues.show', $fuelIssue)
                ->with('error', 'Failed to post fuel issue to accounts: ' . $e->getMessage());
        }
    }

    protected function resolveUnitRateFromStock(StoreStockItem $stockItem): float
    {
        $openingRate = (float) ($stockItem->opening_unit_rate ?? 0);
        if ($openingRate > 0) {
            return round($openingRate, 2);
        }

        $mrLineId = (int) ($stockItem->material_receipt_line_id ?? 0);
        if ($mrLineId <= 0) {
            return 0.0;
        }

        $mrLine = \App\Models\MaterialReceiptLine::query()->find($mrLineId);
        if (! $mrLine) {
            return 0.0;
        }

        $totalBasic = \App\Models\PurchaseBillLine::postedBasicForMaterialReceiptLine($mrLineId);
        if ($totalBasic <= 0) {
            return 0.0;
        }

        $baseQty = (float) ($mrLine->received_weight_kg ?? 0);
        if ($baseQty <= 0) {
            $baseQty = (float) ($mrLine->qty_pcs ?? 0);
        }
        if ($baseQty <= 0) {
            return 0.0;
        }

        return round($totalBasic / $baseQty, 2);
    }

    protected function fuelMaterialTypeCodes(): array
    {
        return ['FUEL', 'FUELS', 'fuel', 'fuels'];
    }

    /**
     * Validate that the selected stock line belongs to material type FUEL.
     */
    protected function ensureFuelStockSelection(StoreStockItem $stockItem): void
    {
        $item = $stockItem->item;
        if (! $item) {
            throw new \RuntimeException('Selected stock item has no linked item master.');
        }

        $typeCode = trim((string) optional($item->type)->code);
        if (! in_array($typeCode, $this->fuelMaterialTypeCodes(), true)) {
            throw new \RuntimeException('Selected stock item material type must be FUEL.');
        }
    }
}
