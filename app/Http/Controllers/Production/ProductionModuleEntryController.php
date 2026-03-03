<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProductionModuleEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function openModule(Request $request, array $config)
    {
        abort_unless(auth()->user()?->can($config['permission'] ?? ''), 403);

        $projectId = (int) $request->integer('project_id');
        if ($projectId > 0) {
            $project = Project::query()->find($projectId);
            if ($project) {
                $request->session()->put('production_project_id', (int) $project->id);
                return redirect()->route($config['route'], ['project' => $project->id]);
            }
        }

        $q = trim((string) $request->get('q', ''));
        $projects = Project::query()
            ->select(['id', 'code', 'name', 'status'])
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', '%' . $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return view('production.module_entry', [
            'moduleTitle' => $config['title'],
            'moduleDescription' => $config['description'],
            'projects' => $projects,
            'q' => $q,
        ]);
    }

    public function dashboard(Request $request)
    {
        return $this->openModule($request, [
            'title' => 'Production Dashboard',
            'description' => 'Select project to open dashboard metrics.',
            'permission' => 'production.report.view',
            'route' => 'projects.production-dashboard.index',
        ]);
    }

    public function billing(Request $request)
    {
        return $this->openModule($request, [
            'title' => 'Production Billing',
            'description' => 'Select project to open billing records and generate bills.',
            'permission' => 'production.billing.view',
            'route' => 'projects.production-billing.index',
        ]);
    }

    public function dispatch(Request $request)
    {
        return $this->openModule($request, [
            'title' => 'Production Dispatch',
            'description' => 'Select project to open dispatch planning and status.',
            'permission' => 'production.dispatch.view',
            'route' => 'projects.production-dispatches.index',
        ]);
    }

    public function traceability(Request $request)
    {
        return $this->openModule($request, [
            'title' => 'Traceability Search',
            'description' => 'Select project to search plate, piece, and assembly traceability.',
            'permission' => 'production.traceability.view',
            'route' => 'projects.production-traceability.index',
        ]);
    }
}
