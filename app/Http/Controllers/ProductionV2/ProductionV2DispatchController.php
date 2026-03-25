<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionDispatch;
use App\Models\ProductionV2\ProductionDispatchLine;
use App\Models\ProductionV2\ProductionReworkEvent;
use App\Support\ProductionV2\RouteProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionV2DispatchController extends Controller
{
    public function __construct(private RouteProgressService $routeProgressService)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.dispatch.view')->only(['index', 'show']);
        $this->middleware('permission:production.dispatch.create')->only(['create', 'store']);
        $this->middleware('permission:production.dispatch.update')->only(['finalize', 'cancel']);
    }

    public function index(Project $project)
    {
        $rows = ProductionDispatch::query()
            ->where('project_id', $project->id)
            ->with(['client', 'finalizedBy'])
            ->withCount('lines')
            ->orderByDesc('id')
            ->paginate(25);

        return view('production_v2.dispatches.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Project $project)
    {
        $eligibleAssemblies = $this->eligibleAssemblies($project);

        return view('production_v2.dispatches.form', [
            'project' => $project,
            'clients' => Party::query()
                ->where('is_client', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'eligibleAssemblies' => $eligibleAssemblies,
            'defaultClientId' => $project->client_party_id,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'client_party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'dispatch_date' => ['required', 'date'],
            'vehicle_number' => ['nullable', 'string', 'max:80'],
            'lr_number' => ['nullable', 'string', 'max:120'],
            'transporter_name' => ['nullable', 'string', 'max:180'],
            'gate_pass_ref' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.assembly_id' => [
                'required',
                'integer',
                Rule::exists('production_v2_assemblies', 'id')->where(fn ($query) => $query->where('project_id', $project->id)->where('status', 'released')),
            ],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.remarks' => ['nullable', 'string'],
        ]);

        $eligibleAssemblies = $this->eligibleAssemblies($project)->keyBy('id');

        $selectedRows = collect($data['lines'] ?? [])
            ->map(function (array $row) use ($eligibleAssemblies) {
                $assemblyId = (int) ($row['assembly_id'] ?? 0);
                $qty = (float) ($row['qty'] ?? 0);
                $eligibility = $eligibleAssemblies->get($assemblyId);

                if (! $eligibility || $qty <= 0) {
                    return null;
                }

                return [
                    'assembly' => $eligibility['assembly'],
                    'qty' => $qty,
                    'remaining_qty' => $eligibility['remaining_qty'],
                    'remarks' => $row['remarks'] ?? null,
                ];
            })
            ->filter()
            ->values();

        if ($selectedRows->isEmpty()) {
            return back()->withInput()->with('error', 'Select at least one dispatch-ready assembly.');
        }

        foreach ($selectedRows as $row) {
            if ($row['qty'] > $row['remaining_qty'] + 0.0001) {
                $assembly = $row['assembly'];

                return back()
                    ->withInput()
                    ->withErrors([
                        'lines' => "Dispatch qty for {$assembly->assembly_code} exceeds remaining dispatchable qty.",
                    ]);
            }
        }

        $dispatch = DB::transaction(function () use ($project, $data, $selectedRows) {
            $dispatch = ProductionDispatch::query()->create([
                'project_id' => $project->id,
                'client_party_id' => $data['client_party_id'] ?? $project->client_party_id,
                'dispatch_number' => ProductionDispatch::nextDispatchNumber($project),
                'dispatch_date' => $data['dispatch_date'],
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'lr_number' => $data['lr_number'] ?? null,
                'transporter_name' => $data['transporter_name'] ?? null,
                'gate_pass_ref' => $data['gate_pass_ref'] ?? null,
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $totalQty = 0.0;
            $totalWeight = 0.0;

            foreach ($selectedRows as $row) {
                /** @var \App\Models\ProductionV2\ProductionAssembly $assembly */
                $assembly = $row['assembly'];
                $unitWeight = $this->assemblyUnitWeight($assembly);
                $lineWeight = round($row['qty'] * $unitWeight, 3);
                $clientDispatchSnapshot = $this->clientDispatchSnapshot($assembly);

                ProductionDispatchLine::query()->create([
                    'dispatch_id' => $dispatch->id,
                    'assembly_id' => $assembly->id,
                    'qty' => $row['qty'],
                    'weight_kg' => $lineWeight,
                    'assembly_code_snapshot' => $assembly->assembly_code,
                    'assembly_name_snapshot' => $assembly->assembly_name,
                    'girder_no_snapshot' => $assembly->girder_no,
                    'segment_no_snapshot' => $assembly->segment_no,
                    'client_dispatch_part_count' => $clientDispatchSnapshot['count'],
                    'client_dispatch_part_codes_snapshot' => $clientDispatchSnapshot['codes'],
                    'client_dispatch_description_snapshot' => $clientDispatchSnapshot['description'],
                    'remarks' => $row['remarks'],
                ]);

                $totalQty += $row['qty'];
                $totalWeight += $lineWeight;
            }

            $dispatch->update([
                'total_qty' => $totalQty,
                'total_weight_kg' => $totalWeight,
            ]);

            return $dispatch;
        });

        return redirect()
            ->route('projects.production-v2.dispatches.show', ['project' => $project->id, 'dispatch' => $dispatch->id])
            ->with('success', 'Production V2 dispatch created.');
    }

    public function show(Project $project, ProductionDispatch $dispatch)
    {
        abort_unless((int) $dispatch->project_id === (int) $project->id, 404);

        $dispatch->load(['client', 'finalizedBy', 'lines.assembly']);

        return view('production_v2.dispatches.show', [
            'project' => $project,
            'dispatch' => $dispatch,
        ]);
    }

    public function finalize(Project $project, ProductionDispatch $dispatch)
    {
        abort_unless((int) $dispatch->project_id === (int) $project->id, 404);

        if (! $dispatch->isDraft()) {
            return back()->with('error', 'Only draft dispatches can be finalized.');
        }

        $dispatch->update([
            'status' => 'finalized',
            'finalized_by' => auth()->id(),
            'finalized_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Production V2 dispatch finalized.');
    }

    public function cancel(Project $project, ProductionDispatch $dispatch)
    {
        abort_unless((int) $dispatch->project_id === (int) $project->id, 404);

        if ($dispatch->isFinalized()) {
            return back()->with('error', 'Finalized dispatch cannot be cancelled.');
        }

        if ($dispatch->isCancelled()) {
            return back()->with('error', 'Dispatch is already cancelled.');
        }

        $dispatch->update([
            'status' => 'cancelled',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Production V2 dispatch cancelled.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,assembly:\App\Models\ProductionV2\ProductionAssembly,remaining_qty:float,already_dispatched_qty:float,open_rework_count:int}>
     */
    private function eligibleAssemblies(Project $project): Collection
    {
        $alreadyDispatched = ProductionDispatchLine::query()
            ->join('production_v2_dispatches as dispatches', 'dispatches.id', '=', 'production_v2_dispatch_lines.dispatch_id')
            ->where('dispatches.project_id', $project->id)
            ->whereIn('dispatches.status', ['draft', 'finalized'])
            ->groupBy('production_v2_dispatch_lines.assembly_id')
            ->selectRaw('production_v2_dispatch_lines.assembly_id as assembly_id, SUM(production_v2_dispatch_lines.qty) as qty_sum')
            ->pluck('qty_sum', 'assembly_id');

        $openReworkCounts = ProductionReworkEvent::query()
            ->where('project_id', $project->id)
            ->whereIn('final_result', ['pending', 'failed', 'reoffer', 'hold'])
            ->whereNotNull('assembly_id')
            ->groupBy('assembly_id')
            ->selectRaw('assembly_id, COUNT(*) as total_rows')
            ->pluck('total_rows', 'assembly_id');

        return ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->where('status', 'released')
            ->with(['routeTemplate', 'routeSteps.operationMaster', 'requirements.partDefinition'])
            ->orderBy('assembly_code')
            ->get()
            ->map(function (ProductionAssembly $assembly) use ($alreadyDispatched, $openReworkCounts) {
                $nextStep = $this->routeProgressService->nextAssemblyStep($assembly);
                $alreadyDispatchedQty = (float) ($alreadyDispatched[$assembly->id] ?? 0);
                $plannedQty = max(0.0, (float) ($assembly->planned_qty ?? 0));
                $remainingQty = max(0.0, $plannedQty - $alreadyDispatchedQty);
                $openReworkCount = (int) ($openReworkCounts[$assembly->id] ?? 0);
                $hasRoute = $assembly->routeSteps->isNotEmpty();

                if (! $hasRoute || $nextStep !== null || $openReworkCount > 0 || $remainingQty <= 0.0001) {
                    return null;
                }

                return [
                    'id' => $assembly->id,
                    'assembly' => $assembly,
                    'remaining_qty' => $remainingQty,
                    'already_dispatched_qty' => $alreadyDispatchedQty,
                    'open_rework_count' => $openReworkCount,
                ];
            })
            ->filter()
            ->values();
    }

    private function assemblyUnitWeight(ProductionAssembly $assembly): float
    {
        $plannedQty = max(1.0, (float) ($assembly->planned_qty ?: 1));
        $plannedWeight = (float) ($assembly->planned_weight_kg ?? 0);

        if ($plannedWeight <= 0) {
            return 0.0;
        }

        return $plannedWeight / $plannedQty;
    }

    /**
     * @return array{count:int,codes:?string,description:?string}
     */
    private function clientDispatchSnapshot(ProductionAssembly $assembly): array
    {
        $parts = $assembly->requirements
            ->where('is_client_dispatchable', true)
            ->map(function ($requirement) {
                $partCode = trim((string) ($requirement->partDefinition?->part_code ?? ''));
                $partName = trim((string) ($requirement->partDefinition?->part_name ?? ''));

                return [
                    'code' => $partCode,
                    'label' => trim($partCode . ($partName !== '' ? ' - ' . $partName : '')),
                ];
            })
            ->filter(fn (array $row) => $row['label'] !== '')
            ->values();

        return [
            'count' => $parts->count(),
            'codes' => $parts->pluck('code')->filter()->unique()->implode(', ') ?: null,
            'description' => $parts->pluck('label')->unique()->implode(', ') ?: null,
        ];
    }
}
