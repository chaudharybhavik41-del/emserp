<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionOperationMaster;
use App\Support\ProductionV2\OperationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductionV2OperationMasterController extends Controller
{
    public function __construct(private OperationCatalog $operationCatalog)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update']);
    }

    public function index(Project $project)
    {
        $this->operationCatalog->ensureDefaults();

        $rows = ProductionOperationMaster::query()
            ->orderBy('applies_to')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30);

        return view('production_v2.operation_masters.index', [
            'project' => $project,
            'rows' => $rows,
            'summary' => [
                'total' => ProductionOperationMaster::query()->count(),
                'part' => ProductionOperationMaster::query()->where('applies_to', 'part')->where('is_active', true)->count(),
                'assembly' => ProductionOperationMaster::query()->where('applies_to', 'assembly')->where('is_active', true)->count(),
                'qc_default' => ProductionOperationMaster::query()->where('requires_qc', true)->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.operation_masters.form', [
            'project' => $project,
            'operationMaster' => new ProductionOperationMaster([
                'applies_to' => 'assembly',
                'entry_mode' => 'generic',
                'is_active' => true,
                'requires_machine' => false,
                'requires_qc' => false,
            ]),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request);

        $operationMaster = ProductionOperationMaster::query()->create($data);

        return redirect()
            ->route('projects.production-v2.operation-masters.show', ['project' => $project->id, 'operationMaster' => $operationMaster->id])
            ->with('success', 'Production V2 operation master created.');
    }

    public function show(Project $project, ProductionOperationMaster $operationMaster)
    {
        return view('production_v2.operation_masters.show', [
            'project' => $project,
            'operationMaster' => $operationMaster->loadCount(['routeTemplateSteps', 'qcGateEvents']),
        ]);
    }

    public function edit(Project $project, ProductionOperationMaster $operationMaster)
    {
        return view('production_v2.operation_masters.form', [
            'project' => $project,
            'operationMaster' => $operationMaster,
        ]);
    }

    public function update(Request $request, Project $project, ProductionOperationMaster $operationMaster)
    {
        $data = $this->validatedData($request, $operationMaster->id, (bool) $operationMaster->is_system);

        $operationMaster->update($data);

        return redirect()
            ->route('projects.production-v2.operation-masters.show', ['project' => $project->id, 'operationMaster' => $operationMaster->id])
            ->with('success', 'Production V2 operation master updated.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null, bool $forceCoreIdentity = false): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:160'],
            'applies_to' => ['required', Rule::in(['part', 'assembly'])],
            'entry_mode' => ['required', Rule::in(['generic', 'specialized'])],
            'entry_route' => ['nullable', 'string', 'max:160'],
            'requires_machine' => ['nullable', 'boolean'],
            'requires_qc' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $data['code'] = Str::of((string) $data['code'])->lower()->snake()->replace('_', '_')->value();

        validator(
            ['code' => $data['code']],
            ['code' => [Rule::unique('production_v2_operation_masters', 'code')->ignore($ignoreId)]]
        )->validate();

        if ($forceCoreIdentity) {
            unset($data['code'], $data['applies_to'], $data['entry_mode'], $data['entry_route']);
        }

        $data['requires_machine'] = (bool) ($data['requires_machine'] ?? false);
        $data['requires_qc'] = (bool) ($data['requires_qc'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
