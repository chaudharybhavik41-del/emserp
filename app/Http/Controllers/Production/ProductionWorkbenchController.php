<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionWorkbenchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.report.view|production.plan.view|production.dpr.view|production.qc.perform|production.billing.view|production.dispatch.view|production.traceability.view');
    }

    public function index(Request $request, ?Project $project = null)
    {
        $projectId = (int) ($project?->id ?? $request->integer('project_id'));
        if (! $project && $projectId > 0) {
            return redirect()->route('production.workbench.project', ['project' => $projectId]);
        }

        $selectedProject = $project;
        if ($selectedProject) {
            $request->session()->put('production_project_id', (int) $selectedProject->id);
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

        $stats = null;
        if ($selectedProject) {
            $projectPk = (int) $selectedProject->id;

            $stats = [
                'plan_approved' => (int) DB::table('production_plans')
                    ->where('project_id', $projectPk)
                    ->where('status', 'approved')
                    ->count(),
                'dpr_draft' => (int) DB::table('production_dprs as d')
                    ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
                    ->where('p.project_id', $projectPk)
                    ->where('d.status', 'draft')
                    ->count(),
                'dpr_today' => (int) DB::table('production_dprs as d')
                    ->join('production_plans as p', 'p.id', '=', 'd.production_plan_id')
                    ->where('p.project_id', $projectPk)
                    ->whereDate('d.dpr_date', now()->toDateString())
                    ->count(),
                'qc_pending' => (int) DB::table('production_qc_checks')
                    ->where('project_id', $projectPk)
                    ->where('result', 'pending')
                    ->count(),
            ];
        }

        return view('production.workbench', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'stats' => $stats,
            'q' => $q,
        ]);
    }
}
