<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\MachineMaintenancePlan;
use App\Models\User;
use App\Services\MaintenanceScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MachineMaintenancePlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:machinery.maintenance_plan.view')->only(['index','show']);
        $this->middleware('permission:machinery.maintenance_plan.create')->only(['create','store']);
        $this->middleware('permission:machinery.maintenance_plan.update')->only(['edit','update','toggle']);
        $this->middleware('permission:machinery.maintenance_plan.delete')->only(['destroy']);
    }

    public function index()
    {
        $plans = MachineMaintenancePlan::with('machine')
            ->latest()
            ->paginate(20);

        return view('machine_maintenance.plans.index', compact('plans'));
    }

    public function create()
    {
        $machines = Machine::where('is_active', true)->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('machine_maintenance.plans.create', compact('machines', 'users'));
    }

    public function store(Request $request, MaintenanceScheduleService $scheduler)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',

            'plan_code' => 'nullable|string|max:50|unique:machine_maintenance_plans,plan_code',
            'plan_name' => 'required|string|max:255',

            'maintenance_type' => 'required|in:preventive,predictive,calibration,inspection',

            'frequency_type' => 'required|in:daily,weekly,monthly,quarterly,half_yearly,yearly,operating_hours',
            'frequency_value' => 'required|integer|min:1',

            'last_executed_date' => 'nullable|date',
            'next_scheduled_date' => 'nullable|date',

            'alert_days_before' => 'nullable|integer|min:0',
            'alert_user_ids' => 'nullable|array',
            'alert_user_ids.*' => 'exists:users,id',

            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'requires_shutdown' => 'nullable|boolean',

            // UI helper field (we will parse it into checklist_items[])
            'checklist_items_text' => 'nullable|string',

            'remarks' => 'nullable|string',
        ]);

        $validated['requires_shutdown'] = $request->boolean('requires_shutdown');

        // Normalize arrays
        $validated['alert_user_ids'] = array_values(array_filter(array_map('intval', $validated['alert_user_ids'] ?? [])));

        $validated['checklist_items'] = $this->parseChecklistItems($validated['checklist_items_text'] ?? null);
        unset($validated['checklist_items_text']);

        // Defaults
        if (empty($validated['plan_code'])) {
            $validated['plan_code'] = MachineMaintenancePlan::generateCode();
        }
        $validated['alert_days_before'] = isset($validated['alert_days_before']) ? (int) $validated['alert_days_before'] : 7;
        $validated['created_by'] = auth()->id();
        $validated['is_active'] = true;

        // If last_executed_date not given, use machine last_maintenance_date (if any)
        if (empty($validated['last_executed_date'])) {
            $machine = Machine::find($validated['machine_id']);
            $validated['last_executed_date'] = optional($machine?->last_maintenance_date)->toDateString();
        }

        $plan = DB::transaction(function () use ($validated) {
            /** @var MachineMaintenancePlan $plan */
            $plan = MachineMaintenancePlan::create($validated);

            // Compute next_scheduled_date if not explicitly set (and not operating_hours)
            if ($plan->frequency_type === 'operating_hours') {
                $plan->next_scheduled_date = null;
            } elseif (empty($plan->next_scheduled_date)) {
                $plan->next_scheduled_date = $plan->calculateNextDate();
            }

            $plan->save();

            return $plan;
        });

        // P2 rule: machine.next_maintenance_due_date is cached summary of plan next dates (or fallback)
        $scheduler->syncMachineNextDueDate((int) $plan->machine_id);

        return redirect()->route('maintenance.plans.index')
            ->with('success', 'Maintenance plan created successfully.');
    }

    public function show(MachineMaintenancePlan $maintenance_plan)
    {
        $maintenance_plan->load('machine');

        return view('machine_maintenance.plans.show', compact('maintenance_plan'));
    }

    public function edit(MachineMaintenancePlan $maintenance_plan)
    {
        $machines = Machine::where('is_active', true)->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('machine_maintenance.plans.edit', compact('maintenance_plan', 'machines', 'users'));
    }

    public function update(Request $request, MachineMaintenancePlan $maintenance_plan, MaintenanceScheduleService $scheduler)
    {
        $oldMachineId = (int) $maintenance_plan->machine_id;

        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',

            'plan_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('machine_maintenance_plans', 'plan_code')->ignore($maintenance_plan->id),
            ],
            'plan_name' => 'required|string|max:255',

            'maintenance_type' => 'required|in:preventive,predictive,calibration,inspection',

            'frequency_type' => 'required|in:daily,weekly,monthly,quarterly,half_yearly,yearly,operating_hours',
            'frequency_value' => 'required|integer|min:1',

            'last_executed_date' => 'nullable|date',
            'next_scheduled_date' => 'nullable|date',

            'alert_days_before' => 'nullable|integer|min:0',
            'alert_user_ids' => 'nullable|array',
            'alert_user_ids.*' => 'exists:users,id',

            'estimated_duration_hours' => 'nullable|numeric|min:0',
            'requires_shutdown' => 'nullable|boolean',

            // UI helper field (we will parse it into checklist_items[])
            'checklist_items_text' => 'nullable|string',

            'remarks' => 'nullable|string',
        ]);

        $validated['requires_shutdown'] = $request->boolean('requires_shutdown');
        $validated['alert_user_ids'] = array_values(array_filter(array_map('intval', $validated['alert_user_ids'] ?? [])));

        $validated['checklist_items'] = $this->parseChecklistItems($validated['checklist_items_text'] ?? null);
        unset($validated['checklist_items_text']);

        $oldFreqType = (string) $maintenance_plan->frequency_type;
        $oldFreqValue = (int) $maintenance_plan->frequency_value;
        $oldLastExec = optional($maintenance_plan->last_executed_date)->toDateString();

        DB::transaction(function () use ($maintenance_plan, $validated, $request, $oldFreqType, $oldFreqValue, $oldLastExec) {
            $maintenance_plan->fill($validated);

            $scheduleFieldsChanged =
                $oldFreqType !== (string) $maintenance_plan->frequency_type ||
                $oldFreqValue !== (int) $maintenance_plan->frequency_value ||
                $oldLastExec !== optional($maintenance_plan->last_executed_date)->toDateString();

            if ($maintenance_plan->frequency_type === 'operating_hours') {
                $maintenance_plan->next_scheduled_date = null;
            } else {
                if ($request->filled('next_scheduled_date')) {
                    $maintenance_plan->next_scheduled_date = $request->input('next_scheduled_date');
                } elseif ($scheduleFieldsChanged || empty($maintenance_plan->next_scheduled_date)) {
                    $maintenance_plan->next_scheduled_date = $maintenance_plan->calculateNextDate();
                }
            }

            $maintenance_plan->save();
        });

        // If the plan was moved to a different machine, re-sync both.
        $newMachineId = (int) $maintenance_plan->machine_id;
        if ($oldMachineId !== $newMachineId) {
            $scheduler->syncMachineNextDueDate($oldMachineId);
        }
        $scheduler->syncMachineNextDueDate($newMachineId);

        return redirect()->route('maintenance.plans.index')
            ->with('success', 'Maintenance plan updated successfully.');
    }

    public function toggle(MachineMaintenancePlan $maintenance_plan, MaintenanceScheduleService $scheduler)
    {
        $maintenance_plan->is_active = ! (bool) $maintenance_plan->is_active;
        $maintenance_plan->save();

        $scheduler->syncMachineNextDueDate((int) $maintenance_plan->machine_id);

        return back()->with('success', 'Maintenance plan status updated.');
    }

    public function destroy(MachineMaintenancePlan $maintenance_plan, MaintenanceScheduleService $scheduler)
    {
        $machineId = (int) $maintenance_plan->machine_id;
        $maintenance_plan->delete();

        $scheduler->syncMachineNextDueDate($machineId);

        return back()->with('success', 'Maintenance plan deleted.');
    }

    private function parseChecklistItems(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $text);
        $lines = array_map('trim', $lines ?? []);
        $lines = array_values(array_filter($lines, fn ($l) => $l !== ''));

        return $lines;
    }
}
