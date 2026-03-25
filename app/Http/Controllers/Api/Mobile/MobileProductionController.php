<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Project;
use App\Models\Production\ProductionDpr;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyRouteStep;
use App\Models\ProductionV2\ProductionCutBatch;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionInspectionEvent;
use App\Models\ProductionV2\ProductionOperationEvent;
use App\Models\ProductionV2\ProductionOperationMaster;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionPartRouteStep;
use App\Models\ProductionV2\ProductionReworkEvent;
use App\Models\ProductionV2\ProductionTrialAssembly;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Models\ProductionV2\ProductionWipItem;
use App\Models\User;
use App\Support\ProductionV2\DailyDprManager;
use App\Support\ProductionV2\OperationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileProductionController extends Controller
{
    public function __construct(
        private DailyDprManager $dprManager,
        private OperationCatalog $operationCatalog
    ) {
    }

    public function projects(Request $request): JsonResponse
    {
        $this->ensureAnyProductionAccess($request);

        $projects = Project::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'code', 'name', 'status', 'production_mode']);

        return response()->json([
            'data' => $projects
                ->map(fn (Project $project): array => $this->serializeProject($project))
                ->values(),
        ]);
    }

    public function formData(Request $request, Project $project): JsonResponse
    {
        $this->ensureAnyProductionAccess($request);
        $this->operationCatalog->ensureDefaults();

        $activityOptions = collect($this->dprManager->activityOptions($project))
            ->map(function (string $label, string $key): array {
                $definition = $this->dprManager->definition($key);

                return [
                    'key' => $key,
                    'label' => $label,
                    'code' => $definition['code'],
                    'requires_machine' => (bool) ($definition['requires_machine'] ?? false),
                    'requires_qc' => (bool) ($definition['requires_qc'] ?? false),
                    'route' => $definition['route'] ?? null,
                ];
            })
            ->values();

        $operations = ProductionOperationMaster::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductionOperationMaster $operation): array => $this->serializeOperationMaster($operation))
            ->values();

        return response()->json([
            'project' => $this->serializeProject($project),
            'activity_options' => $activityOptions,
            'operations' => $operations,
            'contractors' => Party::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'code', 'name'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'code' => $party->code,
                    'name' => $party->name,
                ])
                ->values(),
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values(),
            'machines' => Machine::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->orderBy('name')
                ->limit(250)
                ->get(['id', 'code', 'name', 'status'])
                ->map(fn (Machine $machine): array => [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                    'status' => $machine->status,
                ])
                ->values(),
            'dpr_statuses' => ['draft', 'submitted', 'approved'],
            'event_statuses' => ['draft', 'approved', 'hold'],
        ]);
    }

    public function dprs(Request $request, Project $project): JsonResponse
    {
        $this->ensureDprViewPermission($request);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));
        $activityOptions = $this->dprManager->activityOptions($project);
        $activityKey = trim((string) $request->input('activity_key', ''));

        $query = $this->dprManager->dprQuery($project)
            ->with(['activity', 'contractor', 'worker', 'machine'])
            ->orderByDesc('dpr_date')
            ->orderByDesc('id');

        if ($activityKey !== '' && isset($activityOptions[$activityKey])) {
            $query->where('production_activity_id', $this->dprManager->ensureActivity($activityKey)->id);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $dprs = $query->paginate($perPage);

        return response()->json([
            'data' => $dprs->getCollection()
                ->map(fn (ProductionDpr $dpr): array => $this->serializeDpr($dpr))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $dprs->currentPage(),
                'last_page' => $dprs->lastPage(),
                'per_page' => $dprs->perPage(),
                'total' => $dprs->total(),
            ],
        ]);
    }

    public function dprStore(Request $request, Project $project): JsonResponse
    {
        $this->ensureDprCreatePermission($request);

        $activityOptions = $this->dprManager->activityOptions($project);

        $validated = $request->validate([
            'activity_key' => ['required', Rule::in(array_keys($activityOptions))],
            'dpr_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'worker_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'approved'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $dpr = $this->dprManager->create($project, $validated['activity_key'], $validated);
        $dpr->load(['activity', 'contractor', 'worker', 'machine']);

        return response()->json([
            'message' => 'Production DPR created.',
            'data' => $this->serializeDpr($dpr, true),
        ], 201);
    }

    public function dprShow(Request $request, Project $project, ProductionDpr $productionDpr): JsonResponse
    {
        $this->ensureDprViewPermission($request);
        abort_unless($this->belongsToProject($project, $productionDpr), 404);

        $productionDpr->load(['activity', 'contractor', 'worker', 'machine']);

        return response()->json([
            'data' => $this->serializeDpr($productionDpr, true),
        ]);
    }

    public function operationFormData(Request $request, Project $project): JsonResponse
    {
        $this->ensureAnyProductionAccess($request);
        $this->operationCatalog->ensureDefaults();

        $selectedOperation = $this->resolveOperation($request);
        $selectedPartId = (int) $request->integer('part_definition_id');

        $operations = ProductionOperationMaster::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $parts = collect();
        $assemblies = collect();
        $wipItems = collect();

        if ($selectedOperation?->applies_to === 'part') {
            $parts = ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->whereHas('routeSteps', fn ($query) => $query->where('operation_master_id', $selectedOperation->id))
                ->whereNotIn('status', ['superseded', 'obsolete'])
                ->with('uom')
                ->orderBy('part_code')
                ->get();

            if ($selectedPartId > 0) {
                $wipItems = ProductionWipItem::query()
                    ->where('project_id', $project->id)
                    ->where('part_definition_id', $selectedPartId)
                    ->where('status', 'available')
                    ->with(['uom', 'motherStock'])
                    ->orderBy('piece_no')
                    ->orderBy('lot_no')
                    ->limit(200)
                    ->get();
            }
        } elseif ($selectedOperation?->applies_to === 'assembly') {
            $assemblies = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->whereHas('routeSteps', fn ($query) => $query->where('operation_master_id', $selectedOperation->id))
                ->where('status', 'released')
                ->orderBy('assembly_code')
                ->get();
        }

        return response()->json([
            'project' => $this->serializeProject($project),
            'operations' => $operations
                ->map(fn (ProductionOperationMaster $operation): array => $this->serializeOperationMaster($operation))
                ->values(),
            'selected_operation' => $selectedOperation ? $this->serializeOperationMaster($selectedOperation) : null,
            'parts' => $parts
                ->map(fn (ProductionPartDefinition $part): array => [
                    'id' => $part->id,
                    'part_code' => $part->part_code,
                    'part_name' => $part->part_name,
                    'status' => $part->status,
                    'uom' => $part->uom ? [
                        'id' => $part->uom->id,
                        'name' => $part->uom->name,
                    ] : null,
                ])
                ->values(),
            'assemblies' => $assemblies
                ->map(fn (ProductionAssembly $assembly): array => [
                    'id' => $assembly->id,
                    'assembly_code' => $assembly->assembly_code,
                    'assembly_name' => $assembly->assembly_name,
                    'status' => $assembly->status,
                ])
                ->values(),
            'wip_items' => $wipItems
                ->map(fn (ProductionWipItem $wipItem): array => [
                    'id' => $wipItem->id,
                    'piece_no' => $wipItem->piece_no,
                    'lot_no' => $wipItem->lot_no,
                    'qty' => (float) $wipItem->qty,
                    'status' => $wipItem->status,
                    'uom' => $wipItem->uom ? [
                        'id' => $wipItem->uom->id,
                        'name' => $wipItem->uom->name,
                    ] : null,
                    'mother_stock' => $wipItem->motherStock ? [
                        'id' => $wipItem->motherStock->id,
                        'plate_number' => $wipItem->motherStock->plate_number,
                        'heat_number' => $wipItem->motherStock->heat_number,
                    ] : null,
                ])
                ->values(),
            'event_statuses' => ['draft', 'approved', 'hold'],
        ]);
    }

    public function operationEvents(Request $request, Project $project): JsonResponse
    {
        $this->ensureOperationViewPermission($request);

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $query = ProductionOperationEvent::query()
            ->where('project_id', $project->id)
            ->with(['operationMaster', 'partDefinition', 'assembly', 'wipItem', 'worker', 'machine', 'contractor', 'dpr'])
            ->latest('operation_date')
            ->latest('id');

        if ($request->filled('operation_master_id')) {
            $query->where('operation_master_id', (int) $request->input('operation_master_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('dpr_id')) {
            $query->where('dpr_id', (int) $request->input('dpr_id'));
        }

        $events = $query->paginate($perPage);

        return response()->json([
            'data' => $events->getCollection()
                ->map(fn (ProductionOperationEvent $event): array => $this->serializeOperationEvent($event))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function operationEventStore(Request $request, Project $project): JsonResponse
    {
        $this->ensureOperationCreatePermission($request);

        $validated = $request->validate([
            'operation_master_id' => ['required', 'integer', 'exists:production_v2_operation_masters,id'],
            'dpr_id' => ['nullable', 'integer', 'exists:production_dprs,id'],
            'operation_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'part_definition_id' => ['nullable', 'integer', 'exists:production_v2_part_definitions,id'],
            'assembly_id' => ['nullable', 'integer', 'exists:production_v2_assemblies,id'],
            'wip_item_id' => ['nullable', 'integer', 'exists:production_v2_wip_items,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'worker_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'contractor_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'result' => ['nullable', 'string', 'max:60'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'approved', 'hold'])],
        ]);

        $operation = ProductionOperationMaster::query()->findOrFail((int) $validated['operation_master_id']);
        abort_unless($operation->is_active, 422);

        $partRouteStepId = null;
        $assemblyRouteStepId = null;
        $partId = null;
        $assemblyId = null;
        $uomId = null;

        if ($operation->applies_to === 'part') {
            $partId = (int) ($validated['part_definition_id'] ?? 0);
            abort_if($partId <= 0, 422, 'Part is required for this operation.');

            $part = ProductionPartDefinition::query()
                ->where('project_id', $project->id)
                ->where('id', $partId)
                ->with('uom')
                ->firstOrFail();

            $routeStep = ProductionPartRouteStep::query()
                ->where('part_definition_id', $partId)
                ->where('operation_master_id', $operation->id)
                ->firstOrFail();

            $partRouteStepId = $routeStep->id;
            $uomId = $part->uom_id;
        } else {
            $assemblyId = (int) ($validated['assembly_id'] ?? 0);
            abort_if($assemblyId <= 0, 422, 'Assembly is required for this operation.');

            $assembly = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->where('id', $assemblyId)
                ->firstOrFail();

            $routeStep = ProductionAssemblyRouteStep::query()
                ->where('assembly_id', $assemblyId)
                ->where('operation_master_id', $operation->id)
                ->firstOrFail();

            $assemblyRouteStepId = $routeStep->id;
        }

        $selectedDpr = null;
        if (! empty($validated['dpr_id'])) {
            $selectedDpr = $this->dprManager->findForActivity($project, (int) $validated['dpr_id'], 'op:' . $operation->code);
            if (! $selectedDpr) {
                return response()->json([
                    'message' => 'Selected DPR does not match this operation.',
                ], 422);
            }
        }

        $event = ProductionOperationEvent::query()->create([
            'project_id' => $project->id,
            'operation_master_id' => $operation->id,
            'part_route_step_id' => $partRouteStepId,
            'assembly_route_step_id' => $assemblyRouteStepId,
            'part_definition_id' => $partId ?: null,
            'assembly_id' => $assemblyId ?: null,
            'wip_item_id' => ! empty($validated['wip_item_id']) ? (int) $validated['wip_item_id'] : null,
            'dpr_id' => $selectedDpr?->id,
            'operation_date' => $validated['operation_date'],
            'shift' => $validated['shift'] ?? null,
            'qty' => $validated['qty'],
            'uom_id' => $uomId,
            'machine_id' => $validated['machine_id'] ?? null,
            'worker_user_id' => $validated['worker_user_id'] ?? null,
            'contractor_party_id' => $validated['contractor_party_id'] ?? null,
            'result' => $validated['result'] ?? null,
            'reference_no' => $validated['reference_no'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'],
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $event->load(['operationMaster', 'partDefinition', 'assembly', 'wipItem', 'worker', 'machine', 'contractor', 'dpr']);

        return response()->json([
            'message' => 'Production operation event recorded.',
            'data' => $this->serializeOperationEvent($event, true),
        ], 201);
    }

    public function operationEventShow(Request $request, Project $project, ProductionOperationEvent $operationEvent): JsonResponse
    {
        $this->ensureOperationViewPermission($request);
        abort_unless((int) $operationEvent->project_id === (int) $project->id, 404);

        $operationEvent->load(['operationMaster', 'partDefinition', 'assembly', 'wipItem', 'worker', 'machine', 'contractor', 'dpr']);

        return response()->json([
            'data' => $this->serializeOperationEvent($operationEvent, true),
        ]);
    }

    private function ensureAnyProductionAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->canAny(['production.dpr.view', 'production.dpr.create', 'production.qc.perform']),
            403
        );
    }

    private function ensureDprViewPermission(Request $request): void
    {
        abort_unless(
            $request->user()?->canAny(['production.dpr.view', 'production.dpr.create']),
            403
        );
    }

    private function ensureDprCreatePermission(Request $request): void
    {
        abort_unless($request->user()?->can('production.dpr.create'), 403);
    }

    private function ensureOperationViewPermission(Request $request): void
    {
        abort_unless(
            $request->user()?->canAny(['production.dpr.view', 'production.qc.perform']),
            403
        );
    }

    private function ensureOperationCreatePermission(Request $request): void
    {
        abort_unless(
            $request->user()?->canAny(['production.dpr.create', 'production.qc.perform']),
            403
        );
    }

    private function belongsToProject(Project $project, ProductionDpr $productionDpr): bool
    {
        $plan = $this->dprManager->ensurePlan($project);
        $productionDpr->loadMissing('activity');

        return (int) $productionDpr->production_plan_id === (int) $plan->id
            && $this->dprManager->activityKeyForDpr($productionDpr) !== null;
    }

    private function resolveOperation(Request $request): ?ProductionOperationMaster
    {
        $operationId = (int) $request->integer('operation_master_id');
        if ($operationId > 0) {
            return ProductionOperationMaster::query()
                ->where('id', $operationId)
                ->where('is_active', true)
                ->first();
        }

        $operationCode = trim((string) $request->input('operation_code', ''));
        if ($operationCode !== '') {
            return $this->operationCatalog->findByCode($operationCode);
        }

        return null;
    }

    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
            'production_mode' => $project->production_mode,
        ];
    }

    private function serializeOperationMaster(ProductionOperationMaster $operation): array
    {
        return [
            'id' => $operation->id,
            'code' => $operation->code,
            'name' => $operation->name,
            'activity_key' => 'op:' . $operation->code,
            'applies_to' => $operation->applies_to,
            'entry_mode' => $operation->entry_mode,
            'entry_route' => $operation->entry_route,
            'requires_machine' => (bool) $operation->requires_machine,
            'requires_qc' => (bool) $operation->requires_qc,
        ];
    }

    private function serializeDpr(ProductionDpr $dpr, bool $includeDetails = false): array
    {
        $data = [
            'id' => $dpr->id,
            'dpr_date' => optional($dpr->dpr_date)->toDateString(),
            'shift' => $dpr->shift,
            'status' => $dpr->status,
            'remarks' => $dpr->remarks,
            'activity_key' => $this->dprManager->activityKeyForDpr($dpr),
            'activity' => $dpr->activity ? [
                'id' => $dpr->activity->id,
                'code' => $dpr->activity->code,
                'name' => $dpr->activity->name,
            ] : null,
            'contractor' => $dpr->contractor ? [
                'id' => $dpr->contractor->id,
                'name' => $dpr->contractor->name,
            ] : null,
            'worker' => $dpr->worker ? [
                'id' => $dpr->worker->id,
                'name' => $dpr->worker->name,
            ] : null,
            'machine' => $dpr->machine ? [
                'id' => $dpr->machine->id,
                'code' => $dpr->machine->code,
                'name' => $dpr->machine->name,
            ] : null,
        ];

        if (! $includeDetails) {
            return $data;
        }

        $data['links'] = [
            'cut_batches_count' => ProductionCutBatch::query()->where('dpr_id', $dpr->id)->count(),
            'fitups_count' => ProductionFitup::query()->where('dpr_id', $dpr->id)->count(),
            'welding_events_count' => ProductionWeldingEvent::query()->where('dpr_id', $dpr->id)->count(),
            'inspection_events_count' => ProductionInspectionEvent::query()->where('related_dpr_id', $dpr->id)->count(),
            'rework_events_count' => ProductionReworkEvent::query()->where('rework_dpr_id', $dpr->id)->count(),
            'trial_assemblies_count' => ProductionTrialAssembly::query()->where('dpr_id', $dpr->id)->count(),
            'operation_events_count' => ProductionOperationEvent::query()->where('dpr_id', $dpr->id)->count(),
        ];

        return $data;
    }

    private function serializeOperationEvent(ProductionOperationEvent $event, bool $includeDetails = false): array
    {
        $data = [
            'id' => $event->id,
            'operation_date' => optional($event->operation_date)->toDateString(),
            'shift' => $event->shift,
            'qty' => (float) $event->qty,
            'status' => $event->status,
            'result' => $event->result,
            'reference_no' => $event->reference_no,
            'remarks' => $event->remarks,
            'operation' => $event->operationMaster ? $this->serializeOperationMaster($event->operationMaster) : null,
            'part' => $event->partDefinition ? [
                'id' => $event->partDefinition->id,
                'part_code' => $event->partDefinition->part_code,
                'part_name' => $event->partDefinition->part_name,
            ] : null,
            'assembly' => $event->assembly ? [
                'id' => $event->assembly->id,
                'assembly_code' => $event->assembly->assembly_code,
                'assembly_name' => $event->assembly->assembly_name,
            ] : null,
            'worker' => $event->worker ? [
                'id' => $event->worker->id,
                'name' => $event->worker->name,
            ] : null,
            'machine' => $event->machine ? [
                'id' => $event->machine->id,
                'code' => $event->machine->code,
                'name' => $event->machine->name,
            ] : null,
            'dpr' => $event->dpr ? [
                'id' => $event->dpr->id,
                'status' => $event->dpr->status,
            ] : null,
        ];

        if (! $includeDetails) {
            return $data;
        }

        $data['wip_item'] = $event->wipItem ? [
            'id' => $event->wipItem->id,
            'piece_no' => $event->wipItem->piece_no,
            'lot_no' => $event->wipItem->lot_no,
            'qty' => (float) $event->wipItem->qty,
            'status' => $event->wipItem->status,
        ] : null;

        $data['contractor'] = $event->contractor ? [
            'id' => $event->contractor->id,
            'name' => $event->contractor->name,
        ] : null;

        return $data;
    }
}
