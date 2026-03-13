<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ProductionV2DemandDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view');
    }

    public function index(Project $project)
    {
        $plannedCuts = DB::table('production_v2_cutting_plan_allocations')
            ->select('part_definition_id', DB::raw('SUM(planned_qty) as planned_cut_qty'))
            ->groupBy('part_definition_id');

        $wipTotals = DB::table('production_v2_wip_items')
            ->select(
                'part_definition_id',
                DB::raw('SUM(qty) as cut_qty'),
                DB::raw("SUM(CASE WHEN status IN ('available', 'reserved') THEN qty ELSE 0 END) as available_wip_qty"),
                DB::raw("SUM(CASE WHEN status = 'scrap' THEN qty ELSE 0 END) as scrap_qty")
            )
            ->groupBy('part_definition_id');

        $fitupTotals = DB::table('production_v2_fitup_consumptions')
            ->select('part_definition_id', DB::raw('SUM(consumed_qty) as fitup_consumed_qty'))
            ->groupBy('part_definition_id');

        $rows = DB::table('production_v2_part_definitions as pd')
            ->leftJoinSub($plannedCuts, 'pc', 'pc.part_definition_id', '=', 'pd.id')
            ->leftJoinSub($wipTotals, 'wi', 'wi.part_definition_id', '=', 'pd.id')
            ->leftJoinSub($fitupTotals, 'fc', 'fc.part_definition_id', '=', 'pd.id')
            ->leftJoin('uoms as u', 'u.id', '=', 'pd.uom_id')
            ->where('pd.project_id', $project->id)
            ->orderBy('pd.part_code')
            ->get([
                'pd.id',
                'pd.part_code',
                'pd.part_name',
                'pd.part_type',
                'pd.required_qty',
                'u.code as uom_code',
                DB::raw('COALESCE(pc.planned_cut_qty, 0) as planned_cut_qty'),
                DB::raw('COALESCE(wi.cut_qty, 0) as cut_qty'),
                DB::raw('COALESCE(wi.available_wip_qty, 0) as available_wip_qty'),
                DB::raw('COALESCE(fc.fitup_consumed_qty, 0) as fitup_consumed_qty'),
                DB::raw('COALESCE(wi.scrap_qty, 0) as scrap_qty'),
                DB::raw('(pd.required_qty - COALESCE(fc.fitup_consumed_qty, 0)) as balance_qty'),
            ]);

        return view('production_v2.demand.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }
}
