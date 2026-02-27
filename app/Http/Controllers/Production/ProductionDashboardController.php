<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Production\ProductionBill;
use App\Models\Production\ProductionDpr;
use App\Models\Production\ProductionPiece;
use App\Models\Production\ProductionAssembly;
use App\Models\Production\ProductionPlan;
use App\Models\Production\ProductionPlanItem;
use App\Models\Production\ProductionPlanItemActivity;
use App\Models\Production\ProductionQcCheck;
use App\Models\Production\ProductionRemnant;
use Illuminate\Support\Facades\DB;

class ProductionDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.report.view');
    }

    public function index(Project $project)
    {
        // Plans
        $planCounts = ProductionPlan::query()
            ->where('project_id', $project->id)
            ->selectRaw("SUM(status='draft') as draft_count")
            ->selectRaw("SUM(status='approved') as approved_count")
            ->selectRaw("SUM(status='cancelled') as cancelled_count")
            ->first();

        // Plan Items
        $itemCounts = ProductionPlanItem::query()
            ->whereHas('plan', fn($q) => $q->where('project_id', $project->id))
            ->selectRaw("SUM(status='pending') as pending_count")
            ->selectRaw("SUM(status='in_progress') as in_progress_count")
            ->selectRaw("SUM(status='done') as done_count")
            ->first();

        // Activity WIP breakdown (enabled only)
        $activityWip = ProductionPlanItemActivity::query()
            ->join('production_plan_items as ppi', 'ppi.id', '=', 'production_plan_item_activities.production_plan_item_id')
            ->join('production_plans as pp', 'pp.id', '=', 'ppi.production_plan_id')
            ->join('production_activities as act', 'act.id', '=', 'production_plan_item_activities.production_activity_id')
            ->where('pp.project_id', $project->id)
            ->where('pp.status', 'approved')
            ->where('production_plan_item_activities.is_enabled', 1)
            ->groupBy('act.id', 'act.name')
            ->orderBy('act.name')
            ->select([
                'act.id',
                'act.name',
                DB::raw("SUM(production_plan_item_activities.status='pending') as pending"),
                DB::raw("SUM(production_plan_item_activities.status='in_progress') as in_progress"),
                DB::raw("SUM(production_plan_item_activities.status='done') as done"),
                DB::raw("SUM(production_plan_item_activities.qc_status='pending') as qc_pending"),
                DB::raw("SUM(production_plan_item_activities.qc_status='failed') as qc_failed"),
            ])
            ->get();

        // DPR counts
        $dprCounts = ProductionDpr::query()
            ->whereHas('plan', fn($q) => $q->where('project_id', $project->id))
            ->selectRaw("SUM(status='draft') as draft_count")
            ->selectRaw("SUM(status='submitted') as submitted_count")
            ->selectRaw("SUM(status='approved') as approved_count")
            ->first();

        // QC pending
        $qcPendingCount = ProductionQcCheck::query()
            ->where('project_id', $project->id)
            ->where('result', 'pending')
            ->count();

        // Traceability counts
        $pieceCounts = ProductionPiece::query()
            ->where('project_id', $project->id)
            ->selectRaw("SUM(status='available') as available_count")
            ->selectRaw("SUM(status='consumed') as consumed_count")
            ->selectRaw("SUM(status='scrap') as scrap_count")
            ->first();

        $assemblyCounts = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->selectRaw("SUM(status='in_progress') as in_progress_count")
            ->selectRaw("SUM(status='completed') as completed_count")
            ->first();

        $remnantCounts = ProductionRemnant::query()
            ->where('project_id', $project->id)
            ->selectRaw("SUM(status='available') as available_count")
            ->selectRaw("SUM(is_usable=1 AND status='available') as usable_available_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status='available' THEN weight_kg ELSE 0 END), 0) as available_weight_kg")
            ->first();

        // Billing summary (Phase E1)
        $billSummary = ProductionBill::query()
            ->where('project_id', $project->id)
            ->selectRaw("COUNT(*) as bill_count")
            ->selectRaw("COALESCE(SUM(grand_total), 0) as bill_total")
            ->first();

        // Recent DPRs
        $recentDprs = ProductionDpr::query()
            ->whereHas('plan', fn($q) => $q->where('project_id', $project->id))
            ->with(['activity', 'plan'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('projects.production_dashboard.index', compact(
            'project',
            'planCounts',
            'itemCounts',
            'activityWip',
            'dprCounts',
            'qcPendingCount',
            'pieceCounts',
            'assemblyCounts',
            'remnantCounts',
            'billSummary',
            'recentDprs'
        ));
    }

    public function wipByActivity(Project $project)
    {
        // Detailed list: pending/blocked eligible logic in Phase C is complex;
        // here we show counts and QC pending/failed as “blocked”.
        $rows = ProductionPlanItemActivity::query()
            ->join('production_plan_items as ppi', 'ppi.id', '=', 'production_plan_item_activities.production_plan_item_id')
            ->join('production_plans as pp', 'pp.id', '=', 'ppi.production_plan_id')
            ->join('production_activities as act', 'act.id', '=', 'production_plan_item_activities.production_activity_id')
            ->where('pp.project_id', $project->id)
            ->where('pp.status', 'approved')
            ->where('production_plan_item_activities.is_enabled', 1)
            ->groupBy('act.id', 'act.name')
            ->orderBy('act.name')
            ->select([
                'act.name',
                DB::raw("SUM(production_plan_item_activities.status='pending') as pending"),
                DB::raw("SUM(production_plan_item_activities.status='in_progress') as in_progress"),
                DB::raw("SUM(production_plan_item_activities.status='done') as done"),
                DB::raw("SUM(production_plan_item_activities.qc_status='pending') as qc_pending"),
                DB::raw("SUM(production_plan_item_activities.qc_status='failed') as qc_failed"),
            ])
            ->get();

        return view('projects.production_dashboard.wip_activity', compact('project', 'rows'));
    }

    public function remnants(Project $project)
    {
        $rows = ProductionRemnant::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->paginate(30);

        return view('projects.production_dashboard.remnants', compact('project', 'rows'));
    }
}
