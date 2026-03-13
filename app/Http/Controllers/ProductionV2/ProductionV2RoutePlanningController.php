<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionRouteTemplate;
use App\Support\ProductionV2\RouteSnapshotManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2RoutePlanningController extends Controller
{
    public function __construct(private RouteSnapshotManager $routeSnapshotManager)
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view')->only(['index']);
        $this->middleware('permission:production.plan.update')->only(['updatePart', 'updateAssembly']);
    }

    public function index(Project $project)
    {
        $partTemplates = ProductionRouteTemplate::query()
            ->where('project_id', $project->id)
            ->where('applies_to', 'part')
            ->whereNotIn('status', ['obsolete'])
            ->orderBy('template_code')
            ->get();

        $assemblyTemplates = ProductionRouteTemplate::query()
            ->where('project_id', $project->id)
            ->where('applies_to', 'assembly')
            ->whereNotIn('status', ['obsolete'])
            ->orderBy('template_code')
            ->get();

        $parts = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', ['superseded', 'obsolete'])
            ->with(['uom', 'routeTemplate'])
            ->orderBy('part_code')
            ->paginate(20, ['*'], 'parts_page');

        $assemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', ['superseded', 'closed'])
            ->withCount('requirements')
            ->with('routeTemplate')
            ->orderBy('sequence_no')
            ->orderBy('assembly_code')
            ->paginate(20, ['*'], 'assemblies_page');

        return view('production_v2.route_planning.index', [
            'project' => $project,
            'parts' => $parts,
            'assemblies' => $assemblies,
            'partTemplates' => $partTemplates,
            'assemblyTemplates' => $assemblyTemplates,
            'summary' => [
                'part_total' => ProductionPartDefinition::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'obsolete'])->count(),
                'part_routed' => ProductionPartDefinition::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'obsolete'])->whereNotNull('route_template_id')->count(),
                'assembly_total' => ProductionAssembly::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'closed'])->count(),
                'assembly_routed' => ProductionAssembly::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'closed'])->whereNotNull('route_template_id')->count(),
            ],
        ]);
    }

    public function updatePart(Request $request, Project $project, ProductionPartDefinition $part)
    {
        abort_unless((int) $part->project_id === (int) $project->id, 404);

        $data = $request->validate([
            'route_template_id' => [
                'nullable',
                'integer',
                Rule::exists('production_v2_route_templates', 'id')
                    ->where('project_id', $project->id)
                    ->where('applies_to', 'part'),
            ],
        ]);

        $part->route_template_id = $data['route_template_id'] ?? null;
        $part->save();
        $this->routeSnapshotManager->syncPart($part);

        return redirect()
            ->route('projects.production-v2.route-planning.index', ['project' => $project->id, 'parts_page' => $request->integer('parts_page', 1), 'assemblies_page' => $request->integer('assemblies_page', 1)])
            ->with('success', 'Part route planning updated.');
    }

    public function updateAssembly(Request $request, Project $project, ProductionAssembly $assembly)
    {
        abort_unless((int) $assembly->project_id === (int) $project->id, 404);

        $data = $request->validate([
            'route_template_id' => [
                'nullable',
                'integer',
                Rule::exists('production_v2_route_templates', 'id')
                    ->where('project_id', $project->id)
                    ->where('applies_to', 'assembly'),
            ],
        ]);

        $assembly->route_template_id = $data['route_template_id'] ?? null;
        $assembly->save();
        $this->routeSnapshotManager->syncAssembly($assembly);

        return redirect()
            ->route('projects.production-v2.route-planning.index', ['project' => $project->id, 'parts_page' => $request->integer('parts_page', 1), 'assemblies_page' => $request->integer('assemblies_page', 1)])
            ->with('success', 'Assembly route planning updated.');
    }
}
