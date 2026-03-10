<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Bom;
use App\Models\Production\ProductionPlan;
use App\Models\Production\ProductionPlanItem;
use App\Services\ApprovalNotificationService;
use App\Services\Production\ProductionAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:production.plan.view')->only(['index', 'show']);
        $this->middleware('permission:production.plan.create')->only(['create', 'store']);
        $this->middleware('permission:production.plan.update')->only(['edit', 'update', 'destroy', 'cancel', 'reopen']);
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

        $query = ProductionPlan::with(['project', 'bom']);

        if ($projectId > 0) {
            $query->where('project_id', $projectId);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('plan_number', 'like', '%' . $q . '%')
                    ->orWhereHas('project', function ($projectQuery) use ($q) {
                        $projectQuery->where('code', 'like', '%' . $q . '%')
                            ->orWhere('name', 'like', '%' . $q . '%');
                    })
                    ->orWhereHas('bom', function ($bomQuery) use ($q) {
                        $bomQuery->where('bom_number', 'like', '%' . $q . '%');
                    });
            });
        }

        $status = trim((string) $request->get('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($bomId = (int) $request->get('bom_id', 0)) {
            $query->where('bom_id', $bomId);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['plan_number', 'status', 'approved_at', 'created_at', 'id'];
        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->orderByDesc('id');
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $plans = $query->paginate($perPage)->withQueryString();

        $boms = Bom::query()
            ->select(['id', 'bom_number', 'project_id'])
            ->when($projectId > 0, fn ($builder) => $builder->where('project_id', $projectId))
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('production.plans.index', [
            'plans' => $plans,
            'projects' => $projects,
            'projectId' => $projectId > 0 ? $projectId : null,
            'boms' => $boms,
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

        // Machine gate:
        // Any enabled activity marked as requires_machine must have a valid active machine assigned.
        if (Schema::hasColumn('production_plan_item_activities', 'machine_id')) {
            $hasMachineDeletedAt = Schema::hasColumn('machines', 'deleted_at');

            $missingMachineAssignments = DB::table('production_plan_item_activities as pia')
                ->join('production_plan_items as i', 'i.id', '=', 'pia.production_plan_item_id')
                ->join('production_activities as act', 'act.id', '=', 'pia.production_activity_id')
                ->leftJoin('machines as m', 'm.id', '=', 'pia.machine_id')
                ->where('i.production_plan_id', $plan->id)
                ->where('pia.is_enabled', 1)
                ->where('act.requires_machine', 1)
                ->where(function ($query) use ($hasMachineDeletedAt) {
                    $query->whereNull('pia.machine_id')
                        ->orWhereNull('m.id')
                        ->orWhere('m.is_active', '!=', 1)
                        ->orWhere('m.status', '!=', 'active');
                    if ($hasMachineDeletedAt) {
                        $query->orWhereNotNull('m.deleted_at');
                    }
                })
                ->count();

            if ($missingMachineAssignments > 0) {
                return back()->with('error', 'Some enabled machine-required routes have no valid active machine assigned. Update Route Matrix before approval.');
            }
        }

        $plan->status = 'approved';
        $plan->approved_by = auth()->id();
        $plan->approved_at = now();
        $plan->updated_by = auth()->id();
        $plan->save();

        $notifier = app(ApprovalNotificationService::class);
        $notifier->notifyUserById(
            $plan->created_by,
            'Production Plan Approved',
            "Production Plan {$plan->plan_number} has been approved.",
            [
                'module' => 'production_plan',
                'production_plan_id' => $plan->id,
                'project_id' => $projectId,
            ],
            $notifier->safeRoute('projects.production-plans.show', [
                'project' => $projectId,
                'production_plan' => $plan->id,
            ]),
            'success'
        );

        return redirect(url('/projects/'.$projectId.'/production-plans/'.$plan->id))
            ->with('success', 'Production plan approved.');
    }

    public function cancel(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        $plan = ProductionPlan::query()
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->firstOrFail();

        if ($plan->status === 'cancelled') {
            return back()->with('error', 'Plan is already cancelled.');
        }

        if (! in_array((string) $plan->status, ['draft', 'approved'], true)) {
            return back()->with('error', 'Only draft/approved plans can be cancelled.');
        }

        if ($plan->status === 'approved') {
            $hasLiveDprs = DB::table('production_dprs')
                ->where('production_plan_id', $plan->id)
                ->where('status', '!=', 'cancelled')
                ->exists();

            if ($hasLiveDprs) {
                return back()->with('error', 'Approved plan cannot be cancelled after DPR creation. Cancel DPRs first.');
            }
        }

        $oldStatus = (string) $plan->status;

        $plan->status = 'cancelled';
        $plan->updated_by = auth()->id();
        $plan->save();

        ProductionAudit::log(
            $projectId,
            'plan.cancel',
            'production_plan',
            $plan->id,
            'Plan cancelled',
            [
                'plan_number' => $plan->plan_number,
                'old_status' => $oldStatus,
                'new_status' => 'cancelled',
            ]
        );

        return redirect(url('/projects/' . $projectId . '/production-plans/' . $plan->id))
            ->with('success', 'Production plan cancelled.');
    }

    public function reopen(Request $request, $project, $production_plan)
    {
        $projectId = is_object($project) ? (int) ($project->id ?? 0) : (int) $project;
        $planId = is_object($production_plan) ? (int) ($production_plan->id ?? 0) : (int) $production_plan;

        $plan = ProductionPlan::query()
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->firstOrFail();

        if ($plan->status !== 'cancelled') {
            return back()->with('error', 'Only cancelled plans can be reopened.');
        }

        $plan->status = 'draft';
        $plan->approved_by = null;
        $plan->approved_at = null;
        $plan->updated_by = auth()->id();
        $plan->save();

        ProductionAudit::log(
            $projectId,
            'plan.reopen',
            'production_plan',
            $plan->id,
            'Plan reopened to draft',
            [
                'plan_number' => $plan->plan_number,
                'old_status' => 'cancelled',
                'new_status' => 'draft',
            ]
        );

        return redirect(url('/projects/' . $projectId . '/production-plans/' . $plan->id))
            ->with('success', 'Production plan reopened (draft).');
    }
}
