<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Production\ProductionPlanItemActivity;
use App\Models\Production\ProductionPlanItem;
use App\Models\Production\ProductionQcCheck;
use App\Services\Production\ProductionAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionQcController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.qc.perform');
    }

    public function index(Request $request, ?Project $project = null)
    {
        $projectId = $project?->id ?: (int) $request->integer('project_id');
        $currentProject = $project;

        if (! $currentProject && $projectId > 0) {
            $currentProject = Project::query()->find($projectId);
        }

        $pendingQuery = ProductionQcCheck::query()
            ->where('result', 'pending')
            ->with(['plan.project', 'activity', 'planItemActivity.planItem'])
            ->orderBy('id');

        if ($projectId > 0) {
            $pendingQuery->where('project_id', $projectId);
        }

        $pending = $pendingQuery->paginate(25)->withQueryString();

        $projects = Project::query()
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('projects.production_qc.index', [
            'project' => $currentProject,
            'projectId' => $projectId > 0 ? $projectId : null,
            'projects' => $projects,
            'pending' => $pending,
        ]);
    }

    public function update(Request $request, ProductionQcCheck $qc, ?Project $project = null)
    {
        $projectId = $project?->id ?: (int) $qc->project_id;
        if ($project && (int) $qc->project_id !== (int) $project->id) {
            abort(404);
        }

        $data = $request->validate([
            'result' => ['required', 'in:passed,failed'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        // Idempotent: can't update twice
        if ($qc->result !== 'pending') {
            return back()->with('error', 'QC already completed for this record.');
        }

        $qc->result = $data['result'];
        $qc->remarks = $data['remarks'] ?? null;
        $qc->checked_by = auth()->id();
        $qc->checked_at = now();
        $qc->save();

        $event = $data['result'] === 'passed' ? 'qc.pass' : 'qc.fail';

        // Apply gate result back to plan item activity
        if ($qc->production_plan_item_activity_id) {
            $pia = ProductionPlanItemActivity::find($qc->production_plan_item_activity_id);
            if ($pia) {
                if ($data['result'] === 'passed') {
                    $pia->qc_status = 'passed';
                    $pia->qc_by = auth()->id();
                    $pia->qc_at = now();
                    $pia->qc_remarks = $data['remarks'] ?? null;
                    $pia->status = 'done';
                } else {
                    $pia->qc_status = 'failed';
                    $pia->qc_by = auth()->id();
                    $pia->qc_at = now();
                    $pia->qc_remarks = $data['remarks'] ?? null;
                    $pia->status = 'in_progress';
                }
                $pia->save();

                // Keep parent plan item status in sync once QC gate is completed.
                if ($pia->production_plan_item_id) {
                    $pending = ProductionPlanItemActivity::query()
                        ->where('production_plan_item_id', $pia->production_plan_item_id)
                        ->where('is_enabled', 1)
                        ->where('status', '!=', 'done')
                        ->exists();

                    ProductionPlanItem::query()
                        ->where('id', $pia->production_plan_item_id)
                        ->update([
                            'status' => $pending ? 'in_progress' : 'done',
                            'updated_at' => now(),
                        ]);

                    if (! $pending && Schema::hasTable('production_assemblies')) {
                        DB::table('production_assemblies')
                            ->where('production_plan_item_id', (int) $pia->production_plan_item_id)
                            ->where('status', '!=', 'completed')
                            ->update([
                                'status' => 'completed',
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
        }

        ProductionAudit::log(
            $projectId,
            $event,
            'ProductionQcCheck',
            $qc->id,
            'QC updated',
            ['result' => $data['result']]
        );

        return back()->with('success', 'QC updated.');
    }
}

