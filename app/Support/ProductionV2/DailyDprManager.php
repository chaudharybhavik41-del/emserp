<?php

namespace App\Support\ProductionV2;

use App\Models\Project;
use App\Models\Production\ProductionActivity;
use App\Models\Production\ProductionDpr;
use App\Models\Production\ProductionPlan;
use App\Models\ProductionV2\ProductionOperationMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DailyDprManager
{
    public function __construct(
        private OperationCatalog $operationCatalog,
        private RouteProgressService $routeProgressService
    )
    {
    }

    private const ACTIVITY_DEFINITIONS = [
        'cutting' => [
            'code' => 'PV2_CUTTING',
            'name' => 'Production V2 Cutting',
            'requires_machine' => true,
            'requires_qc' => false,
            'route' => 'projects.production-v2.cut-batches.create',
            'date_field' => 'cut_date',
            'worker_field' => 'operator_id',
        ],
        'fitup' => [
            'code' => 'PV2_FITUP',
            'name' => 'Production V2 Fit-up',
            'requires_machine' => false,
            'requires_qc' => false,
            'route' => 'projects.production-v2.fitups.create',
            'date_field' => 'fitup_date',
            'worker_field' => 'supervisor_id',
        ],
        'welding' => [
            'code' => 'PV2_WELDING',
            'name' => 'Production V2 Welding',
            'requires_machine' => true,
            'requires_qc' => false,
            'route' => 'projects.production-v2.welding-events.create',
            'date_field' => 'weld_date',
            'worker_field' => 'welder_id',
        ],
        'inspection' => [
            'code' => 'PV2_INSPECTION',
            'name' => 'Production V2 Inspection',
            'requires_machine' => false,
            'requires_qc' => true,
            'route' => 'projects.production-v2.inspection-events.create',
            'date_field' => 'inspection_date',
            'worker_field' => 'checked_by',
        ],
        'rework' => [
            'code' => 'PV2_REWORK',
            'name' => 'Production V2 Rework',
            'requires_machine' => false,
            'requires_qc' => true,
            'route' => 'projects.production-v2.rework-events.create',
            'date_field' => 'rework_date',
            'worker_field' => null,
        ],
        'trial_assembly' => [
            'code' => 'PV2_TRIAL_ASSEMBLY',
            'name' => 'Production V2 Trial Assembly',
            'requires_machine' => false,
            'requires_qc' => true,
            'route' => 'projects.production-v2.trial-assemblies.create',
            'date_field' => 'trial_date',
            'worker_field' => 'checked_by',
        ],
    ];

    public function activityKeys(Project $project): array
    {
        return array_merge(array_keys(self::ACTIVITY_DEFINITIONS), $this->genericActivityKeys($project));
    }

    public function activityOptions(Project $project): array
    {
        $base = collect(self::ACTIVITY_DEFINITIONS)
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['name']]);

        $generic = collect($this->genericActivityKeys($project))
            ->mapWithKeys(function (string $key) {
                $definition = $this->definition($key);

                return [$key => $definition['name']];
            });

        return $base
            ->merge($generic)
            ->all();
    }

    public function definition(string $activityKey): array
    {
        if (str_starts_with($activityKey, 'op:')) {
            $operation = $this->operationCatalog->findByCode(substr($activityKey, 3));
            if (! $operation) {
                throw new InvalidArgumentException('Unsupported Production V2 DPR activity: ' . $activityKey);
            }

            return [
                'code' => 'PV2_OP_' . strtoupper($operation->code),
                'name' => 'Production V2 ' . $operation->name,
                'requires_machine' => (bool) $operation->requires_machine,
                'requires_qc' => (bool) $operation->requires_qc,
                'route' => $operation->entry_route ?: 'projects.production-v2.operation-events.create',
                'date_field' => 'operation_date',
                'worker_field' => 'worker_user_id',
                'applies_to' => $operation->applies_to,
                'default_sequence' => (int) $operation->sort_order,
            ];
        }

        if (! isset(self::ACTIVITY_DEFINITIONS[$activityKey])) {
            throw new InvalidArgumentException('Unsupported Production V2 DPR activity: ' . $activityKey);
        }

        return self::ACTIVITY_DEFINITIONS[$activityKey];
    }

    public function ensurePlan(Project $project): ProductionPlan
    {
        return ProductionPlan::query()->firstOrCreate(
            ['plan_number' => $this->planNumber($project)],
            [
                'project_id' => $project->id,
                'plan_date' => now()->toDateString(),
                'remarks' => 'Auto-managed Production V2 execution shell.',
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    public function ensureActivity(string $activityKey): ProductionActivity
    {
        $definition = $this->definition($activityKey);

        return ProductionActivity::query()->firstOrCreate(
            ['code' => $definition['code']],
            [
                'name' => $definition['name'],
                'applies_to' => $definition['applies_to'] ?? (in_array($activityKey, ['cutting'], true) ? 'part' : 'assembly'),
                'default_sequence' => $definition['default_sequence'] ?? (array_search($activityKey, array_keys(self::ACTIVITY_DEFINITIONS), true) + 1),
                'calculation_method' => 'manual',
                'is_fitupp' => $activityKey === 'fitup',
                'requires_machine' => $definition['requires_machine'],
                'requires_qc' => $definition['requires_qc'],
                'is_active' => true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    public function dprQuery(Project $project): Builder
    {
        $plan = $this->ensurePlan($project);
        $activityIds = collect($this->activityKeys($project))
            ->map(fn (string $key) => $this->ensureActivity($key)->id)
            ->all();

        return ProductionDpr::query()
            ->where('production_plan_id', $plan->id)
            ->whereIn('production_activity_id', $activityIds);
    }

    public function findForActivity(Project $project, int $dprId, string $activityKey): ?ProductionDpr
    {
        if ($dprId <= 0) {
            return null;
        }

        $plan = $this->ensurePlan($project);
        $activity = $this->ensureActivity($activityKey);

        return ProductionDpr::query()
            ->where('id', $dprId)
            ->where('production_plan_id', $plan->id)
            ->where('production_activity_id', $activity->id)
            ->with(['activity', 'contractor', 'worker', 'machine'])
            ->first();
    }

    public function create(Project $project, string $activityKey, array $attributes): ProductionDpr
    {
        $plan = $this->ensurePlan($project);
        $activity = $this->ensureActivity($activityKey);
        $status = (string) ($attributes['status'] ?? 'draft');
        $userId = auth()->id();

        $payload = [
            'production_plan_id' => $plan->id,
            'production_activity_id' => $activity->id,
            'dpr_date' => $attributes['dpr_date'],
            'shift' => $attributes['shift'] ?? null,
            'contractor_party_id' => $attributes['contractor_party_id'] ?? null,
            'worker_user_id' => $attributes['worker_user_id'] ?? null,
            'machine_id' => $attributes['machine_id'] ?? null,
            'remarks' => $attributes['remarks'] ?? null,
            'status' => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        if (in_array($status, ['submitted', 'approved'], true)) {
            $payload['submitted_by'] = $userId;
            $payload['submitted_at'] = now();
        }

        if ($status === 'approved') {
            $payload['approved_by'] = $userId;
            $payload['approved_at'] = now();
        }

        return ProductionDpr::query()->create($payload);
    }

    public function activityKeyForDpr(?ProductionDpr $dpr): ?string
    {
        if (! $dpr) {
            return null;
        }

        $code = strtoupper((string) ($dpr->activity?->code ?? ''));
        foreach (self::ACTIVITY_DEFINITIONS as $key => $definition) {
            if ($definition['code'] === $code) {
                return $key;
            }
        }

        if (str_starts_with($code, 'PV2_OP_')) {
            return 'op:' . strtolower(substr($code, 7));
        }

        return null;
    }

    public function routeForActivity(string $activityKey): string
    {
        return $this->definition($activityKey)['route'];
    }

    public function routeParametersForActivity(string $activityKey): array
    {
        if (str_starts_with($activityKey, 'op:')) {
            return [
                'operation_code' => substr($activityKey, 3),
                'activity_key' => $activityKey,
            ];
        }

        return [];
    }

    public function dateFieldForActivity(string $activityKey): string
    {
        return $this->definition($activityKey)['date_field'];
    }

    public function workerFieldForActivity(string $activityKey): ?string
    {
        return $this->definition($activityKey)['worker_field'];
    }

    public function recentForActivity(Project $project, string $activityKey, int $limit = 20): Collection
    {
        $activity = $this->ensureActivity($activityKey);

        return $this->dprQuery($project)
            ->where('production_activity_id', $activity->id)
            ->with(['activity', 'contractor', 'worker', 'machine'])
            ->latest('dpr_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function planNumber(Project $project): string
    {
        return 'PV2-EXEC-' . $project->id;
    }

    private function genericActivityKeys(Project $project): array
    {
        $operationIds = $this->routeProgressService
            ->routeAwareActivityOptions($project, $this->operationCatalog)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($operationIds->isEmpty()) {
            return [];
        }

        return ProductionOperationMaster::query()
            ->whereIn('id', $operationIds)
            ->where('entry_mode', 'generic')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('code')
            ->map(fn (string $code) => 'op:' . strtolower($code))
            ->all();
    }
}
