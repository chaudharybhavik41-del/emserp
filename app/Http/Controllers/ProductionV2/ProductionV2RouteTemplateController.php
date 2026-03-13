<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionRouteTemplate;
use App\Models\ProductionV2\ProductionRouteTemplateStep;
use App\Support\ProductionV2\OperationCatalog;
use App\Support\ProductionV2\QcGateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionV2RouteTemplateController extends Controller
{
    public function __construct(
        private OperationCatalog $operationCatalog,
        private QcGateCatalog $qcGateCatalog
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update']);
    }

    public function index(Project $project)
    {
        $templates = ProductionRouteTemplate::query()
            ->where('project_id', $project->id)
            ->withCount('steps')
            ->orderBy('applies_to')
            ->orderBy('template_code')
            ->paginate(25);

        return view('production_v2.route_templates.index', [
            'project' => $project,
            'templates' => $templates,
            'summary' => [
                'total' => ProductionRouteTemplate::query()->where('project_id', $project->id)->count(),
                'part' => ProductionRouteTemplate::query()->where('project_id', $project->id)->where('applies_to', 'part')->whereNotIn('status', ['obsolete'])->count(),
                'assembly' => ProductionRouteTemplate::query()->where('project_id', $project->id)->where('applies_to', 'assembly')->whereNotIn('status', ['obsolete'])->count(),
                'active' => ProductionRouteTemplate::query()->where('project_id', $project->id)->whereIn('status', ['approved', 'active'])->count(),
            ],
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.route_templates.form', $this->formData($project, new ProductionRouteTemplate([
            'applies_to' => 'assembly',
            'status' => 'draft',
        ])));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request, $project);

        $template = DB::transaction(function () use ($project, $data) {
            $template = ProductionRouteTemplate::query()->create([
                'project_id' => $project->id,
                'template_code' => $data['template_code'],
                'template_name' => $data['template_name'],
                'applies_to' => $data['applies_to'],
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncSteps($template, $data['steps']);

            return $template;
        });

        return redirect()
            ->route('projects.production-v2.route-templates.show', ['project' => $project->id, 'routeTemplate' => $template->id])
            ->with('success', 'Production V2 route template created.');
    }

    public function show(Project $project, ProductionRouteTemplate $routeTemplate)
    {
        abort_unless((int) $routeTemplate->project_id === (int) $project->id, 404);
        $routeTemplate->load(['steps.operationMaster']);

        return view('production_v2.route_templates.show', [
            'project' => $project,
            'routeTemplate' => $routeTemplate,
        ]);
    }

    public function edit(Project $project, ProductionRouteTemplate $routeTemplate)
    {
        abort_unless((int) $routeTemplate->project_id === (int) $project->id, 404);
        $routeTemplate->load('steps');

        return view('production_v2.route_templates.form', $this->formData($project, $routeTemplate));
    }

    public function update(Request $request, Project $project, ProductionRouteTemplate $routeTemplate)
    {
        abort_unless((int) $routeTemplate->project_id === (int) $project->id, 404);

        $data = $this->validatedData($request, $project, $routeTemplate->id);

        DB::transaction(function () use ($routeTemplate, $data) {
            $routeTemplate->update([
                'template_code' => $data['template_code'],
                'template_name' => $data['template_name'],
                'applies_to' => $data['applies_to'],
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $this->syncSteps($routeTemplate, $data['steps']);
        });

        return redirect()
            ->route('projects.production-v2.route-templates.show', ['project' => $project->id, 'routeTemplate' => $routeTemplate->id])
            ->with('success', 'Production V2 route template updated.');
    }

    private function formData(Project $project, ProductionRouteTemplate $routeTemplate): array
    {
        return [
            'project' => $project,
            'routeTemplate' => $routeTemplate,
            'partOperations' => $this->operationCatalog->activeOptions('part'),
            'assemblyOperations' => $this->operationCatalog->activeOptions('assembly'),
            'gateModes' => $this->qcGateCatalog->modeOptions(),
            'gateTypes' => $this->qcGateCatalog->typeOptions(),
        ];
    }

    private function validatedData(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'template_code' => ['required', 'string', 'max:120'],
            'template_name' => ['required', 'string', 'max:180'],
            'applies_to' => ['required', Rule::in(['part', 'assembly'])],
            'status' => ['required', Rule::in(['draft', 'approved', 'active', 'obsolete'])],
            'remarks' => ['nullable', 'string'],
            'steps' => ['nullable', 'array'],
            'steps.*.operation_master_id' => ['required', 'integer', 'exists:production_v2_operation_masters,id'],
            'steps.*.sequence_no' => ['nullable', 'integer', 'min:1'],
            'steps.*.is_mandatory' => ['nullable', 'boolean'],
            'steps.*.qc_gate_required' => ['nullable', 'boolean'],
            'steps.*.qc_gate_mode' => ['nullable', Rule::in(array_keys($this->qcGateCatalog->modeOptions()))],
            'steps.*.qc_gate_type' => ['nullable', Rule::in(array_keys($this->qcGateCatalog->typeOptions()))],
            'steps.*.qc_gate_remarks' => ['nullable', 'string'],
            'steps.*.remarks' => ['nullable', 'string'],
        ]);

        $uniqueRule = Rule::unique('production_v2_route_templates', 'template_code')
            ->where('project_id', $project->id);
        if ($ignoreId) {
            $uniqueRule = $uniqueRule->ignore($ignoreId);
        }

        validator(['template_code' => $data['template_code']], ['template_code' => [$uniqueRule]])->validate();

        $data['steps'] = collect($data['steps'] ?? [])
            ->filter(fn ($row) => ! empty($row['operation_master_id']))
            ->values()
            ->all();

        $allowedOperationIds = $this->operationCatalog
            ->activeOptions($data['applies_to'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        validator(
            ['steps' => $data['steps']],
            ['steps.*.operation_master_id' => [Rule::in($allowedOperationIds)]]
        )->validate();

        $messages = [];
        foreach ($data['steps'] as $index => $row) {
            $gateRequired = (bool) ($row['qc_gate_required'] ?? false);
            $mode = $row['qc_gate_mode'] ?? null;
            $type = $row['qc_gate_type'] ?? null;

            if ($gateRequired && (blank($mode) || blank($type))) {
                $messages["steps.$index.qc_gate_mode"] = 'Gate mode and gate type are required when QC gate is enabled.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $data;
    }

    private function syncSteps(ProductionRouteTemplate $template, array $steps): void
    {
        ProductionRouteTemplateStep::query()->where('route_template_id', $template->id)->delete();

        foreach ($steps as $index => $row) {
            ProductionRouteTemplateStep::query()->create([
                'route_template_id' => $template->id,
                'operation_master_id' => (int) $row['operation_master_id'],
                'sequence_no' => (int) ($row['sequence_no'] ?? ($index + 1)),
                'is_mandatory' => array_key_exists('is_mandatory', $row) ? (bool) $row['is_mandatory'] : true,
                'qc_gate_required' => array_key_exists('qc_gate_required', $row) ? (bool) $row['qc_gate_required'] : false,
                'qc_gate_mode' => ! empty($row['qc_gate_required']) ? ($row['qc_gate_mode'] ?? null) : null,
                'qc_gate_type' => ! empty($row['qc_gate_required']) ? ($row['qc_gate_type'] ?? null) : null,
                'qc_gate_remarks' => ! empty($row['qc_gate_required']) ? ($row['qc_gate_remarks'] ?? null) : null,
                'remarks' => $row['remarks'] ?? null,
            ]);
        }
    }
}
