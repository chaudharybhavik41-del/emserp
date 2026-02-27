<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Bom;
use App\Models\Production\ProductionPlan;
use App\Models\Production\ProductionPlanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update', 'destroy']);
        $this->middleware('permission:production.plan.approve')->only(['approve']);
    }

    /**
     * IMPORTANT: Your routes are project-scoped:
     * /projects/{project}/production-plans
     * /projects/{project}/production-plans/{production_plan}
     *
     * Also your {project} and {production_plan} are NOT model-bound in production,
     * so Laravel passes strings. We tolerate both cases.
     */
    public function index(Request $request, $project = null)
    {
        $projectId = 0;

        if ($project !== null) {
            $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        } else {
            // fallback (if index is also exposed non-project-scoped somewhere)
            $projectId = (int) ($request->integer('project_id') ?: 0);
        }

        $projects = Project::orderBy('code')->orderBy('name')->get();

        $query = ProductionPlan::with(['project', 'bom'])
            ->orderByDesc('id');

        if ($projectId > 0) {
            $query->where('project_id', $projectId);
        }

        $plans = $query->paginate(25)->withQueryString();

        return view('production.plans.index', [
            'plans' => $plans,
            'projects' => $projects,
            'projectId' => $projectId > 0 ? $projectId : null,
        ]);
    }

    public function show(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId    = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        $plan = ProductionPlan::with(['project', 'bom'])
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->firstOrFail();

        $items = ProductionPlanItem::where('production_plan_id', $plan->id)
            ->orderBy('level')
            ->orderBy('sequence_no')
            ->orderBy('id')
            ->get();

        $stats = [
            'items_total' => $items->count(),
            'items_pending' => $items->where('status', 'pending')->count(),
            'items_in_progress' => $items->where('status', 'in_progress')->count(),
            'items_done' => $items->where('status', 'done')->count(),
        ];

        return view('production.plans.show', [
            'plan' => $plan,
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    /**
     * Resource create route exists; guide users to the canonical BOM flow.
     */
    public function create(Request $request, $project)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;

        return redirect()
            ->route('projects.production-plans.from-bom', ['project' => $projectId])
            ->with('info', 'Create production plan from approved BOM.');
    }

    /**
     * Direct store is intentionally not used in current workflow.
     */
    public function store(Request $request, $project)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;

        return redirect()
            ->route('projects.production-plans.from-bom', ['project' => $projectId])
            ->with('error', 'Use "Create from BOM" to create production plans.');
    }

    public function edit(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        return redirect()
            ->route('projects.production-plans.show', ['project' => $projectId, 'production_plan' => $planId])
            ->with('info', 'Edit route configuration from plan details.');
    }

    public function update(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        return redirect()
            ->route('projects.production-plans.show', ['project' => $projectId, 'production_plan' => $planId])
            ->with('error', 'Plan header update is not supported. Update routing/items instead.');
    }

    public function destroy(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        return redirect()
            ->route('projects.production-plans.show', ['project' => $projectId, 'production_plan' => $planId])
            ->with('error', 'Deleting production plans is disabled.');
    }

    /**
     * Approve (project-scoped).
     */
    public function approve(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId    = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        $plan = ProductionPlan::where('id', $planId)
            ->where('project_id', $projectId)
            ->firstOrFail();

        if ($plan->status !== 'draft') {
            return back()->with('error', 'Only draft plans can be approved.');
        }

        $itemCount = ProductionPlanItem::where('production_plan_id', $plan->id)->count();
        if ($itemCount <= 0) {
            return back()->with('error', 'Plan has no items. Create plan from BOM first.');
        }

        // Routing validation:
        // - Normal rule: every plan item must have at least 1 enabled activity
        // - Exception: "container assemblies" (assemblies that contain sub-assemblies) may have no route,
        //   because work will happen at lower-level assemblies/parts.
        $missingRoutes = DB::table('production_plan_items as i')
            ->leftJoin('production_plan_item_activities as a', function ($join) {
                $join->on('a.production_plan_item_id', '=', 'i.id')
                    ->where('a.is_enabled', '=', 1);
            })
            ->leftJoin('bom_items as bi', 'bi.id', '=', 'i.bom_item_id')
            ->where('i.production_plan_id', $plan->id)
            // Exclude container assemblies from "must-have-route" requirement.
            ->where(function ($q) {
                $q->where('i.item_type', '!=', 'assembly')
                    ->orWhereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('bom_items as child')
                            ->whereColumn('child.parent_item_id', 'bi.id')
                            ->where('child.material_category', '=', 'fabricated_assembly')
                            ->whereNull('child.deleted_at');
                    });
            })
            ->groupBy('i.id')
            ->havingRaw('COUNT(a.id) = 0')
            ->select('i.id') // prevents duplicate id columns in subquery
            ->count();


        if ($missingRoutes > 0) {
            return back()->with('error', 'Some plan items have no enabled activity route. Configure routing first.');
        }

        $plan->status = 'approved';
        $plan->approved_by = auth()->id();
        $plan->approved_at = now();
        $plan->updated_by = auth()->id();
        $plan->save();

        return redirect(url('/projects/'.$projectId.'/production-plans/'.$plan->id))
            ->with('success', 'Production plan approved.');
    }
}


