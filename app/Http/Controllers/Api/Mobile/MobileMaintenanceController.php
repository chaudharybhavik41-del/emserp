<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\MachineBreakdownRegister;
use App\Models\MachineMaintenanceLog;
use App\Models\MachineMaintenancePlan;
use App\Models\Party;
use App\Models\User;
use App\Services\MaintenanceScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileMaintenanceController extends Controller
{
    public function formData(Request $request): JsonResponse
    {
        $this->ensureAnyMaintenanceAccess($request);

        return response()->json([
            'machines' => Machine::query()
                ->where('is_active', true)
                ->whereNotIn('status', ['retired', 'disposed'])
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'status', 'current_project_id'])
                ->map(fn (Machine $machine): array => [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'status' => $machine->status,
                    'current_project_id' => $machine->current_project_id,
                ])
                ->values(),
            'plans' => MachineMaintenancePlan::query()
                ->where('is_active', true)
                ->orderBy('plan_name')
                ->get(['id', 'machine_id', 'plan_code', 'plan_name', 'maintenance_type', 'next_scheduled_date'])
                ->map(fn (MachineMaintenancePlan $plan): array => [
                    'id' => $plan->id,
                    'machine_id' => $plan->machine_id,
                    'plan_code' => $plan->plan_code,
                    'plan_name' => $plan->plan_name,
                    'maintenance_type' => $plan->maintenance_type,
                    'next_scheduled_date' => optional($plan->next_scheduled_date)->toDateString(),
                ])
                ->values(),
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values(),
            'vendors' => Party::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'code' => $party->code,
                    'name' => $party->name,
                ])
                ->values(),
            'maintenance_types' => ['preventive', 'breakdown', 'predictive', 'calibration', 'inspection'],
            'log_statuses' => ['scheduled', 'in_progress', 'completed', 'deferred', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'breakdown_types' => ['mechanical', 'electrical', 'hydraulic', 'software', 'operator_error', 'other'],
            'breakdown_severities' => ['minor', 'major', 'critical'],
        ]);
    }

    public function plans(Request $request): JsonResponse
    {
        $this->ensurePlanViewPermission($request);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $query = MachineMaintenancePlan::query()
            ->with('machine')
            ->latest();

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', (int) $request->input('machine_id'));
        }

        if ($request->input('due') === 'soon') {
            $query->whereNotNull('next_scheduled_date')
                ->whereDate('next_scheduled_date', '<=', now()->addDays(7));
        }

        if ($request->boolean('overdue')) {
            $query->whereNotNull('next_scheduled_date')
                ->whereDate('next_scheduled_date', '<', now());
        }

        $plans = $query->paginate($perPage);

        return response()->json([
            'data' => $plans->getCollection()
                ->map(fn (MachineMaintenancePlan $plan): array => $this->serializePlan($plan))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function planShow(Request $request, MachineMaintenancePlan $maintenancePlan): JsonResponse
    {
        $this->ensurePlanViewPermission($request);

        $maintenancePlan->load('machine');

        return response()->json([
            'data' => $this->serializePlan($maintenancePlan, true),
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $this->ensureLogViewPermission($request);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $query = MachineMaintenanceLog::query()
            ->with(['machine', 'plan', 'contractor'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', (int) $request->input('machine_id'));
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->getCollection()
                ->map(fn (MachineMaintenanceLog $log): array => $this->serializeLog($log))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function logShow(Request $request, MachineMaintenanceLog $maintenanceLog): JsonResponse
    {
        $this->ensureLogViewPermission($request);

        $maintenanceLog->load(['machine', 'plan', 'contractor', 'spares.item', 'spares.uom', 'spares.storeIssue']);

        return response()->json([
            'data' => $this->serializeLog($maintenanceLog, true),
        ]);
    }

    public function logStore(Request $request): JsonResponse
    {
        $this->ensureLogCreatePermission($request);

        $validated = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'maintenance_plan_id' => [
                'nullable',
                Rule::exists('machine_maintenance_plans', 'id')->where(function ($query) use ($request) {
                    $machineId = (int) $request->input('machine_id');

                    return $query->where('machine_id', $machineId);
                }),
            ],
            'maintenance_type' => ['required', 'in:preventive,breakdown,predictive,calibration,inspection'],
            'scheduled_date' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'status' => ['required', 'in:scheduled,in_progress,completed,deferred,cancelled'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'work_description' => ['required', 'string'],
            'work_performed' => ['nullable', 'string'],
            'findings' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'technician_user_ids' => ['nullable', 'array'],
            'technician_user_ids.*' => ['exists:users,id'],
            'external_vendor_party_id' => ['nullable', 'exists:parties,id'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'external_service_cost' => ['nullable', 'numeric', 'min:0'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0'],
            'meter_reading_before' => ['nullable', 'numeric', 'min:0'],
            'meter_reading_after' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $machine = Machine::findOrFail((int) $validated['machine_id']);

        $activeAssignment = MachineAssignment::query()
            ->where('machine_id', $machine->id)
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
        $validated['created_by'] = $request->user()?->id;

        if (empty($validated['started_at'])) {
            if (! empty($validated['scheduled_date'])) {
                $validated['started_at'] = Carbon::parse((string) $validated['scheduled_date'])->startOfDay();
            } else {
                $validated['started_at'] = now();
            }
        }

        if ($validated['status'] === 'completed' && empty($validated['completed_at'])) {
            $validated['completed_at'] = now();
            $validated['completed_by'] = $request->user()?->id;
        } elseif ($validated['status'] !== 'completed') {
            $validated['completed_by'] = null;
        }

        $validated['parts_cost'] = 0;
        $validated['labor_cost'] = (float) ($validated['labor_cost'] ?? 0);
        $validated['external_service_cost'] = (float) ($validated['external_service_cost'] ?? 0);
        $validated['total_cost'] = (float) $validated['labor_cost'] + (float) $validated['external_service_cost'];

        $log = MachineMaintenanceLog::create($validated);

        $log->updatePartsCost();

        if ($log->status === 'completed') {
            $this->applyCompletionSideEffects($log);
        }

        $log->load(['machine', 'plan', 'contractor']);

        return response()->json([
            'message' => 'Maintenance log created.',
            'data' => $this->serializeLog($log, true),
        ], 201);
    }

    public function logComplete(Request $request, MachineMaintenanceLog $maintenanceLog): JsonResponse
    {
        $this->ensureLogCompletePermission($request);

        $validated = $request->validate([
            'completed_at' => ['nullable', 'date'],
        ]);

        $maintenanceLog->update([
            'status' => 'completed',
            'completed_at' => ! empty($validated['completed_at'])
                ? Carbon::parse((string) $validated['completed_at'])
                : now(),
            'completed_by' => $request->user()?->id,
        ]);

        $this->applyCompletionSideEffects($maintenanceLog);
        $maintenanceLog->refresh()->load(['machine', 'plan', 'contractor']);

        return response()->json([
            'message' => 'Maintenance log marked as completed.',
            'data' => $this->serializeLog($maintenanceLog, true),
        ]);
    }

    public function breakdowns(Request $request): JsonResponse
    {
        $this->ensureBreakdownViewPermission($request);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $query = MachineBreakdownRegister::query()
            ->with('machine')
            ->latest('reported_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', (string) $request->input('severity'));
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_id', (int) $request->input('machine_id'));
        }

        $breakdowns = $query->paginate($perPage);

        return response()->json([
            'data' => $breakdowns->getCollection()
                ->map(fn (MachineBreakdownRegister $breakdown): array => $this->serializeBreakdown($breakdown))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $breakdowns->currentPage(),
                'last_page' => $breakdowns->lastPage(),
                'per_page' => $breakdowns->perPage(),
                'total' => $breakdowns->total(),
            ],
        ]);
    }

    public function breakdownShow(Request $request, MachineBreakdownRegister $breakdown): JsonResponse
    {
        $this->ensureBreakdownViewPermission($request);

        $breakdown->load(['machine', 'reporter', 'acknowledger', 'resolver', 'maintenanceLog']);

        return response()->json([
            'data' => $this->serializeBreakdown($breakdown, true),
        ]);
    }

    public function breakdownStore(Request $request): JsonResponse
    {
        $this->ensureBreakdownCreatePermission($request);

        $validated = $request->validate([
            'machine_id' => ['required', 'exists:machines,id'],
            'reported_at' => ['required', 'date'],
            'breakdown_type' => ['required', 'in:mechanical,electrical,hydraulic,software,operator_error,other'],
            'severity' => ['required', 'in:minor,major,critical'],
            'problem_description' => ['required', 'string'],
            'immediate_action_taken' => ['nullable', 'string'],
        ]);

        $breakdown = MachineBreakdownRegister::create([
            'breakdown_number' => MachineBreakdownRegister::generateNumber(),
            'machine_id' => $validated['machine_id'],
            'reported_at' => $validated['reported_at'],
            'breakdown_type' => $validated['breakdown_type'],
            'severity' => $validated['severity'],
            'problem_description' => $validated['problem_description'],
            'immediate_action_taken' => $validated['immediate_action_taken'] ?? null,
            'reported_by' => $request->user()?->id,
            'status' => 'reported',
        ]);

        $breakdown->machine?->update(['status' => 'breakdown']);
        $breakdown->load(['machine', 'reporter']);

        return response()->json([
            'message' => 'Breakdown reported.',
            'data' => $this->serializeBreakdown($breakdown, true),
        ], 201);
    }

    public function breakdownAcknowledge(Request $request, MachineBreakdownRegister $breakdown): JsonResponse
    {
        $this->ensureBreakdownAcknowledgePermission($request);

        if ($breakdown->status !== 'reported') {
            return response()->json([
                'message' => 'Only reported breakdowns can be acknowledged.',
            ], 422);
        }

        $breakdown->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()?->id,
        ]);

        $breakdown->refresh()->load(['machine', 'reporter', 'acknowledger', 'resolver', 'maintenanceLog']);

        return response()->json([
            'message' => 'Breakdown acknowledged.',
            'data' => $this->serializeBreakdown($breakdown, true),
        ]);
    }

    public function breakdownAssignTeam(Request $request, MachineBreakdownRegister $breakdown): JsonResponse
    {
        $this->ensureBreakdownAcknowledgePermission($request);

        $validated = $request->validate([
            'maintenance_team_assigned' => ['required', 'array', 'min:1'],
            'maintenance_team_assigned.*' => ['exists:users,id'],
        ]);

        $breakdown->update([
            'maintenance_team_assigned' => $validated['maintenance_team_assigned'],
            'status' => $breakdown->status === 'reported' ? 'acknowledged' : $breakdown->status,
        ]);

        $breakdown->refresh()->load(['machine', 'reporter', 'acknowledger', 'resolver', 'maintenanceLog']);

        return response()->json([
            'message' => 'Maintenance team assigned.',
            'data' => $this->serializeBreakdown($breakdown, true),
        ]);
    }

    public function breakdownStartRepair(Request $request, MachineBreakdownRegister $breakdown): JsonResponse
    {
        $this->ensureBreakdownAcknowledgePermission($request);

        if (! in_array($breakdown->status, ['reported', 'acknowledged'], true)) {
            return response()->json([
                'message' => 'Repair can only start for reported or acknowledged breakdowns.',
            ], 422);
        }

        $breakdown->update([
            'status' => 'in_progress',
            'repair_started_at' => now(),
        ]);

        $breakdown->machine?->update(['status' => 'under_maintenance']);
        $breakdown->refresh()->load(['machine', 'reporter', 'acknowledger', 'resolver', 'maintenanceLog']);

        return response()->json([
            'message' => 'Repair started.',
            'data' => $this->serializeBreakdown($breakdown, true),
        ]);
    }

    public function breakdownResolve(Request $request, MachineBreakdownRegister $breakdown): JsonResponse
    {
        $this->ensureBreakdownResolvePermission($request);

        if ($breakdown->status !== 'in_progress') {
            return response()->json([
                'message' => 'Only in-progress breakdowns can be resolved.',
            ], 422);
        }

        $validated = $request->validate([
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'repair_notes' => ['nullable', 'string'],
        ]);

        $breakdown->update([
            'status' => 'resolved',
            'repair_completed_at' => now(),
            'resolved_by' => $request->user()?->id,
            'root_cause' => $validated['root_cause'] ?? null,
            'corrective_action' => $validated['corrective_action'] ?? null,
            'repair_notes' => $validated['repair_notes'] ?? null,
        ]);

        $breakdown->machine?->update(['status' => 'active']);
        $breakdown->refresh()->load(['machine', 'reporter', 'acknowledger', 'resolver', 'maintenanceLog']);

        return response()->json([
            'message' => 'Breakdown resolved.',
            'data' => $this->serializeBreakdown($breakdown, true),
        ]);
    }

    private function applyCompletionSideEffects(MachineMaintenanceLog $log): void
    {
        $log->load(['machine', 'plan']);

        $completedDate = optional($log->completed_at)->toDateString() ?? now()->toDateString();

        if ($log->plan) {
            $log->plan->last_executed_date = $completedDate;
            $log->plan->next_scheduled_date = $log->plan->calculateNextDate();
            $log->plan->save();
        }

        $machine = $log->machine;
        if (! $machine) {
            return;
        }

        $machine->last_maintenance_date = $completedDate;
        $machine->save();

        MaintenanceScheduleService::syncMachineNextDueDate((int) $machine->id);
    }

    private function serializePlan(MachineMaintenancePlan $plan, bool $includeDetails = false): array
    {
        $data = [
            'id' => $plan->id,
            'plan_code' => $plan->plan_code,
            'plan_name' => $plan->plan_name,
            'maintenance_type' => $plan->maintenance_type,
            'frequency_type' => $plan->frequency_type,
            'frequency_value' => (int) $plan->frequency_value,
            'is_active' => (bool) $plan->is_active,
            'last_executed_date' => optional($plan->last_executed_date)->toDateString(),
            'next_scheduled_date' => optional($plan->next_scheduled_date)->toDateString(),
            'is_due' => $plan->isDue(),
            'is_overdue' => $plan->isOverdue(),
            'machine' => $plan->machine ? [
                'id' => $plan->machine->id,
                'code' => $plan->machine->code,
                'name' => $plan->machine->name,
            ] : null,
        ];

        if ($includeDetails) {
            $data['estimated_duration_hours'] = $plan->estimated_duration_hours !== null ? (float) $plan->estimated_duration_hours : null;
            $data['requires_shutdown'] = (bool) $plan->requires_shutdown;
            $data['alert_days_before'] = (int) $plan->alert_days_before;
            $data['alert_user_ids'] = $plan->alert_user_ids ?? [];
            $data['checklist_items'] = $plan->checklist_items ?? [];
            $data['remarks'] = $plan->remarks;
        }

        return $data;
    }

    private function serializeLog(MachineMaintenanceLog $log, bool $includeDetails = false): array
    {
        $data = [
            'id' => $log->id,
            'log_number' => $log->log_number,
            'maintenance_type' => $log->maintenance_type,
            'status' => $log->status,
            'priority' => $log->priority,
            'scheduled_date' => optional($log->scheduled_date)->toDateString(),
            'started_at' => optional($log->started_at)->toIso8601String(),
            'completed_at' => optional($log->completed_at)->toIso8601String(),
            'total_cost' => (float) ($log->total_cost ?? 0),
            'machine' => $log->machine ? [
                'id' => $log->machine->id,
                'code' => $log->machine->code,
                'name' => $log->machine->name,
            ] : null,
            'plan' => $log->plan ? [
                'id' => $log->plan->id,
                'plan_code' => $log->plan->plan_code,
                'plan_name' => $log->plan->plan_name,
            ] : null,
        ];

        if ($includeDetails) {
            $data['work_description'] = $log->work_description;
            $data['work_performed'] = $log->work_performed;
            $data['findings'] = $log->findings;
            $data['recommendations'] = $log->recommendations;
            $data['technician_user_ids'] = $log->technician_user_ids ?? [];
            $data['labor_cost'] = (float) ($log->labor_cost ?? 0);
            $data['parts_cost'] = (float) ($log->parts_cost ?? 0);
            $data['external_service_cost'] = (float) ($log->external_service_cost ?? 0);
            $data['downtime_hours'] = (float) ($log->downtime_hours ?? 0);
            $data['remarks'] = $log->remarks;
            $data['spares'] = $log->relationLoaded('spares')
                ? $log->spares->map(fn ($spare): array => [
                    'id' => $spare->id,
                    'item_id' => $spare->item_id,
                    'item_name' => $spare->item?->name,
                    'uom_name' => $spare->uom?->name,
                    'qty_consumed' => (float) ($spare->qty_consumed ?? 0),
                    'unit_cost' => (float) ($spare->unit_cost ?? 0),
                    'total_cost' => (float) ($spare->total_cost ?? 0),
                ])->values()->all()
                : [];
        }

        return $data;
    }

    private function serializeBreakdown(MachineBreakdownRegister $breakdown, bool $includeDetails = false): array
    {
        $data = [
            'id' => $breakdown->id,
            'breakdown_number' => $breakdown->breakdown_number,
            'status' => $breakdown->status,
            'severity' => $breakdown->severity,
            'breakdown_type' => $breakdown->breakdown_type,
            'reported_at' => optional($breakdown->reported_at)->toIso8601String(),
            'machine' => $breakdown->machine ? [
                'id' => $breakdown->machine->id,
                'code' => $breakdown->machine->code,
                'name' => $breakdown->machine->name,
                'status' => $breakdown->machine->status,
            ] : null,
            'problem_description' => $breakdown->problem_description,
        ];

        if ($includeDetails) {
            $data['immediate_action_taken'] = $breakdown->immediate_action_taken;
            $data['maintenance_team_assigned'] = $breakdown->maintenance_team_assigned ?? [];
            $data['repair_started_at'] = optional($breakdown->repair_started_at)->toIso8601String();
            $data['repair_completed_at'] = optional($breakdown->repair_completed_at)->toIso8601String();
            $data['root_cause'] = $breakdown->root_cause;
            $data['corrective_action'] = $breakdown->corrective_action;
            $data['repair_notes'] = $breakdown->repair_notes;
            $data['reporter'] = $breakdown->reporter ? [
                'id' => $breakdown->reporter->id,
                'name' => $breakdown->reporter->name,
            ] : null;
            $data['acknowledger'] = $breakdown->acknowledger ? [
                'id' => $breakdown->acknowledger->id,
                'name' => $breakdown->acknowledger->name,
            ] : null;
            $data['resolver'] = $breakdown->resolver ? [
                'id' => $breakdown->resolver->id,
                'name' => $breakdown->resolver->name,
            ] : null;
        }

        return $data;
    }

    private function ensureAnyMaintenanceAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->can('machinery.maintenance_plan.view')
            || $user?->can('machinery.maintenance_log.create')
            || $user?->can('machinery.breakdown.create')
            || $user?->can('machinery.breakdown.view'),
            403
        );
    }

    private function ensurePlanViewPermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.maintenance_plan.view'), 403);
    }

    private function ensureLogViewPermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.maintenance_log.view'), 403);
    }

    private function ensureLogCreatePermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.maintenance_log.create'), 403);
    }

    private function ensureLogCompletePermission(Request $request): void
    {
        abort_unless(
            $request->user()?->can('machinery.maintenance_log.complete')
            || $request->user()?->can('machinery.maintenance_log.update'),
            403
        );
    }

    private function ensureBreakdownViewPermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.breakdown.view'), 403);
    }

    private function ensureBreakdownCreatePermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.breakdown.create'), 403);
    }

    private function ensureBreakdownAcknowledgePermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.breakdown.acknowledge'), 403);
    }

    private function ensureBreakdownResolvePermission(Request $request): void
    {
        abort_unless($request->user()?->can('machinery.breakdown.resolve'), 403);
    }
}
