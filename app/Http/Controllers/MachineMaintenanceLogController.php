<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\MachineMaintenanceLog;
use App\Models\MachineMaintenancePlan;
use App\Models\MachineSpareConsumption;
use App\Models\MaterialReceiptLine;
use App\Models\Party;
use App\Models\PurchaseBillLine;
use App\Models\StoreIssue;
use App\Models\StoreIssueLine;
use App\Models\User;
use App\Services\MaintenanceScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MachineMaintenanceLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:machinery.maintenance_log.view')->only(['index','show','calendar']);
        $this->middleware('permission:machinery.maintenance_log.create')->only(['create','store','addSpare']);
        $this->middleware('permission:machinery.maintenance_log.update')->only(['edit','update','complete']);
        $this->middleware('permission:machinery.maintenance_log.delete')->only(['destroy']);
    }

    public function index()
    {
        $logs = MachineMaintenanceLog::with(['machine','plan','contractor'])
            ->latest()
            ->paginate(20);

        return view('machine_maintenance.logs.index', compact('logs'));
    }

    public function create()
    {
        $machines = Machine::where('is_active', true)->orderBy('name')->get();
        $plans = MachineMaintenancePlan::where('is_active', true)->orderBy('plan_name')->get();
        $users = User::orderBy('name')->get();
        $vendors = Party::orderBy('name')->get();

        return view('machine_maintenance.logs.create', compact('machines', 'plans', 'users', 'vendors'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'maintenance_plan_id' => 'nullable|exists:machine_maintenance_plans,id',

            // DB enum: preventive, breakdown, predictive, calibration, inspection
            'maintenance_type' => 'required|in:preventive,breakdown,predictive,calibration,inspection',

            'scheduled_date' => 'nullable|date',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',

            // DB enum includes deferred,cancelled
            'status' => 'required|in:scheduled,in_progress,completed,deferred,cancelled',

            // DB enum includes critical
            'priority' => 'nullable|in:low,medium,high,critical',

            'work_description' => 'required|string',
            'work_performed' => 'nullable|string',
            'findings' => 'nullable|string',
            'recommendations' => 'nullable|string',

            'technician_user_ids' => 'nullable|array',
            'technician_user_ids.*' => 'exists:users,id',

            'external_vendor_party_id' => 'nullable|exists:parties,id',

            'labor_cost' => 'nullable|numeric|min:0',
            'external_service_cost' => 'nullable|numeric|min:0',
            'downtime_hours' => 'nullable|numeric|min:0',

            'meter_reading_before' => 'nullable|numeric|min:0',
            'meter_reading_after' => 'nullable|numeric|min:0',

            'remarks' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);

        // Attach current active assignment (for contractor cost attribution)
        $activeAssignment = MachineAssignment::where('machine_id', $machine->id)
            ->where('status', 'active')
            ->latest('assigned_date')
            ->first();

        $validated['machine_assignment_id'] = $activeAssignment?->id;
        $validated['contractor_party_id'] = ($activeAssignment && $activeAssignment->assignment_type === 'contractor')
            ? $activeAssignment->contractor_party_id
            : null;
        $validated['worker_user_id'] = ($activeAssignment && $activeAssignment->assignment_type === 'company_worker')
            ? $activeAssignment->worker_user_id
            : null;

        $validated['log_number'] = MachineMaintenanceLog::generateLogNumber();
        $validated['created_by'] = auth()->id();

        // Normalize started_at (DB requires NOT NULL)
        if (empty($validated['started_at'])) {
            if (! empty($validated['scheduled_date'])) {
                $validated['started_at'] = Carbon::parse($validated['scheduled_date'])->startOfDay();
            } else {
                $validated['started_at'] = now();
            }
        }

        // Normalize completed fields if status is completed
        if ($validated['status'] === 'completed' && empty($validated['completed_at'])) {
            $validated['completed_at'] = now();
            $validated['completed_by'] = auth()->id();
        } elseif ($validated['status'] !== 'completed') {
            // avoid accidentally setting completed_by for non-completed statuses
            $validated['completed_by'] = null;
        }

        // Parts cost is computed strictly from Store Issue (no manual spare costing)
        $validated['parts_cost'] = 0;

        // Compute totals
        $labor = (float)($validated['labor_cost'] ?? 0);
        $parts = 0.0;
        $external = (float)($validated['external_service_cost'] ?? 0);
        $validated['total_cost'] = $labor + $parts + $external;

        $log = DB::transaction(function () use ($validated) {
            return MachineMaintenanceLog::create($validated);
        });

        // Sync totals from any linked spare consumptions (none on create, but safe)
        $log->updatePartsCost();

        // If completed, update plan schedule + machine maintenance dates
        if ($log->status === 'completed') {
            $this->applyCompletionSideEffects($log);
        }

        return redirect()->route('maintenance.logs.show', $log)->with('success', 'Maintenance log created.');
    }

    public function show(MachineMaintenanceLog $maintenance_log)
    {
        $maintenance_log->load([
            'machine',
            'plan',
            'contractor',
            'spares.item',
            'spares.uom',
            'spares.storeIssue',
        ]);

        // For importing spare consumption (mandatory Store Issue source)
        $storeIssues = StoreIssue::orderByDesc('issue_date')->limit(200)->get();

        return view('machine_maintenance.logs.show', [
            'log' => $maintenance_log,
            'storeIssues' => $storeIssues,
        ]);
    }

    public function edit(MachineMaintenanceLog $maintenance_log)
    {
        $machines = Machine::where('is_active', true)->orderBy('name')->get();
        $plans = MachineMaintenancePlan::where('is_active', true)->orderBy('plan_name')->get();
        $users = User::orderBy('name')->get();
        $vendors = Party::orderBy('name')->get();

        return view('machine_maintenance.logs.edit', compact('maintenance_log', 'machines', 'plans', 'users', 'vendors'));
    }

    public function update(Request $request, MachineMaintenanceLog $maintenance_log)
    {
        $validated = $request->validate([
            'maintenance_plan_id' => 'nullable|exists:machine_maintenance_plans,id',
            'maintenance_type' => 'required|in:preventive,breakdown,predictive,calibration,inspection',

            'scheduled_date' => 'nullable|date',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',

            'status' => 'required|in:scheduled,in_progress,completed,deferred,cancelled',
            'priority' => 'nullable|in:low,medium,high,critical',

            'work_description' => 'required|string',
            'work_performed' => 'nullable|string',
            'findings' => 'nullable|string',
            'recommendations' => 'nullable|string',

            'technician_user_ids' => 'nullable|array',
            'technician_user_ids.*' => 'exists:users,id',

            'external_vendor_party_id' => 'nullable|exists:parties,id',

            'labor_cost' => 'nullable|numeric|min:0',
            'external_service_cost' => 'nullable|numeric|min:0',
            'downtime_hours' => 'nullable|numeric|min:0',

            'meter_reading_before' => 'nullable|numeric|min:0',
            'meter_reading_after' => 'nullable|numeric|min:0',

            'remarks' => 'nullable|string',
        ]);

        // Ensure started_at is never null
        if (empty($validated['started_at'])) {
            $validated['started_at'] = $maintenance_log->started_at ?: now();
        }

        if ($validated['status'] === 'completed' && empty($validated['completed_at'])) {
            $validated['completed_at'] = now();
            $validated['completed_by'] = auth()->id();
        } elseif ($validated['status'] !== 'completed') {
            $validated['completed_by'] = null;
        }

        $maintenance_log->update($validated);

        // Recalculate parts_cost + total_cost from spare consumptions (store issues)
        $maintenance_log->updatePartsCost();

        if ($maintenance_log->status === 'completed') {
            $this->applyCompletionSideEffects($maintenance_log);
        }

        return redirect()->route('maintenance.logs.show', $maintenance_log)->with('success', 'Maintenance log updated.');
    }

    public function complete(Request $request, MachineMaintenanceLog $maintenance_log)
    {
        $request->validate([
            'completed_at' => 'nullable|date',
        ]);

        $maintenance_log->update([
            'status' => 'completed',
            'completed_at' => $request->input('completed_at') ? Carbon::parse($request->input('completed_at')) : now(),
            'completed_by' => auth()->id(),
        ]);

        $this->applyCompletionSideEffects($maintenance_log);

        return redirect()->route('maintenance.logs.show', $maintenance_log)->with('success', 'Log marked as completed.');
    }

    /**
     * Import spare consumption from Store Issue (MANDATORY source of parts consumption).
     * - store_issue_id is required.
     * - lines are pulled from store_issue_lines
     * - costing follows the same purchase valuation logic used in accounting posting.
     */
    public function addSpare(Request $request, MachineMaintenanceLog $maintenance_log)
    {
        $validated = $request->validate([
            'store_issue_id' => 'required|exists:store_issues,id',
        ]);

        $storeIssueId = (int) $validated['store_issue_id'];

        DB::transaction(function () use ($maintenance_log, $storeIssueId) {
            $issue = StoreIssue::with(['lines.stockItem', 'lines.item', 'lines.uom'])
                ->findOrFail($storeIssueId);

            // Prevent double-import: replace existing lines for this (log + store_issue)
            MachineSpareConsumption::where('machine_maintenance_log_id', $maintenance_log->id)
                ->where('store_issue_id', $storeIssueId)
                ->delete();

            foreach ($issue->lines as $line) {
                $qty = (float) ($line->issued_weight_kg ?? 0);
                if ($qty <= 0) {
                    $qty = (float) ($line->issued_qty_pcs ?? 0);
                }
                if ($qty <= 0) {
                    continue;
                }

                $totalCost = $this->resolveStoreIssueLineValue($line);
                $unitCost = $qty > 0 ? round($totalCost / $qty, 2) : 0;

                MachineSpareConsumption::create([
                    'machine_maintenance_log_id' => $maintenance_log->id,
                    'machine_id' => $maintenance_log->machine_id,
                    'store_issue_id' => $storeIssueId,
                    'item_id' => $line->item_id,
                    'uom_id' => $line->uom_id,
                    'qty_consumed' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'remarks' => $line->remarks ?: null,
                ]);
            }
        });

        // Recalculate parts_cost + total_cost after import
        $maintenance_log->updatePartsCost();

        return back()->with('success', 'Spare consumption imported from Store Issue.');
    }

    public function calendar()
    {
        $logs = MachineMaintenanceLog::with('machine')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_date')
            ->limit(200)
            ->get();

        return view('machine_maintenance.logs.calendar', compact('logs'));
    }

    public function destroy(MachineMaintenanceLog $maintenance_log)
    {
        $maintenance_log->delete();

        return redirect()->route('maintenance.logs.index')->with('success', 'Maintenance log deleted.');
    }

    /**
     * Apply completion side-effects:
     * - Update plan last_executed_date + next_scheduled_date (plan-level truth)
     * - Update machine last_maintenance_date
     * - Sync machine next_maintenance_due_date cache using MaintenanceScheduleService rules
     */
    private function applyCompletionSideEffects(MachineMaintenanceLog $log): void
    {
        $log->load(['machine','plan']);

        $completedDate = optional($log->completed_at)->toDateString() ?? now()->toDateString();

        // Update plan if linked
        if ($log->plan) {
            $log->plan->last_executed_date = $completedDate;
            $log->plan->next_scheduled_date = $log->plan->calculateNextDate();
            $log->plan->save();
        }

        // Update machine maintenance dates (do NOT directly set next_maintenance_due_date here)
        $machine = $log->machine;
        $machine->last_maintenance_date = $completedDate;
        $machine->save();

        // Recompute machine-level due cache based on active plans or fallback frequency
        MaintenanceScheduleService::syncMachineNextDueDate($machine->id);
    }

    /**
     * Resolve the value of a store issue line based on purchase cost.
     * (Copied from accounting posting logic to keep costing consistent.)
     */
    private function resolveStoreIssueLineValue(StoreIssueLine $line): float
    {
        $stockItem = $line->stockItem;
        if (! $stockItem) {
            return 0.0;
        }

        $mrLineId = $stockItem->material_receipt_line_id ?? null;

        if (! $mrLineId) {
            // Opening / manual stock: use opening_unit_rate if available
            $rate = (float) ($stockItem->opening_unit_rate ?? 0);
            if ($rate <= 0) {
                return 0.0;
            }

            $issuedWeight = (float) ($line->issued_weight_kg ?? 0);
            $issuedPcs    = (float) ($line->issued_qty_pcs ?? 0);

            $issueQty = $issuedWeight > 0 ? $issuedWeight : $issuedPcs;
            if ($issueQty <= 0) {
                return 0.0;
            }

            return round($rate * $issueQty, 2);
        }

        $mrLine = MaterialReceiptLine::find($mrLineId);
        if (! $mrLine) {
            return 0.0;
        }

        $totalBasic = (float) PurchaseBillLine::where('material_receipt_line_id', $mrLineId)->sum('basic_amount');
        if ($totalBasic <= 0) {
            return 0.0;
        }

        // Base quantity for average rate
        $baseQty = 0.0;
        $receivedWeight = (float) ($mrLine->received_weight_kg ?? 0);
        $receivedPcs    = (float) ($mrLine->qty_pcs ?? 0);

        if ($receivedWeight > 0) {
            $baseQty = $receivedWeight;
        } elseif ($receivedPcs > 0) {
            $baseQty = $receivedPcs;
        }

        if ($baseQty <= 0) {
            return 0.0;
        }

        $avgRate = $totalBasic / $baseQty;

        // Issued quantity (prefer weight, fall back to pieces)
        $issuedWeight = (float) ($line->issued_weight_kg ?? 0);
        $issuedPcs    = (float) ($line->issued_qty_pcs ?? 0);

        $issueQty = $issuedWeight > 0 ? $issuedWeight : $issuedPcs;
        if ($issueQty <= 0) {
            return 0.0;
        }

        $amount = $avgRate * $issueQty;

        return round($amount, 2);
    }
}
