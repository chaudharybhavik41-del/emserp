<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProductionV2ModuleEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view|production.plan.create|production.plan.update|production.dpr.view|production.dpr.create|production.qc.perform')
            ->only(['index']);
        $this->middleware('permission:production.plan.view|production.plan.create|production.plan.update')
            ->only(['design']);
        $this->middleware('permission:production.plan.update|production.dpr.view|production.dpr.create|production.qc.perform')
            ->only(['production']);
    }

    public function index()
    {
        return view('production_v2.module_landing');
    }

    public function design(Request $request)
    {
        return $this->projectSelector(
            $request,
            'production-v2.project.design',
            'Production V2 Design Module',
            'Select project to open the design-owned workspace for part list, assembly BOM, cutting plan / nesting, and material planning.',
            'Open Design Module'
        );
    }

    public function production(Request $request)
    {
        return $this->projectSelector(
            $request,
            'production-v2.project',
            'Production V2 Production Module',
            'Select project to open the production-owned workspace for route planning, process masters, execution, WIP, fit-up, welding, inspection, and DPR-driven activity.',
            'Open Production Module'
        );
    }

    protected function projectSelector(Request $request, string $targetRoute, string $title, string $description, string $buttonLabel)
    {
        $projectId = (int) $request->integer('project_id');
        if ($projectId > 0) {
            $project = Project::query()->find($projectId);
            if ($project) {
                $request->session()->put('production_v2_project_id', (int) $project->id);

                return redirect()->route($targetRoute, ['project' => $project->id]);
            }
        }

        $q = trim((string) $request->get('q', ''));
        $projects = Project::query()
            ->select(['id', 'code', 'name', 'status', 'production_mode'])
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', '%' . $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->orderByRaw("CASE WHEN COALESCE(production_mode, 'legacy_only') = 'v2_enabled' THEN 0 WHEN COALESCE(production_mode, 'legacy_only') = 'legacy_to_v2_transition' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return view('production_v2.module_entry', [
            'moduleTitle' => $title,
            'moduleDescription' => $description,
            'buttonLabel' => $buttonLabel,
            'projects' => $projects,
            'q' => $q,
        ]);
    }
}
