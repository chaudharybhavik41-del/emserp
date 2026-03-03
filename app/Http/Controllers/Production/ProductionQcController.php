<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Production\ProductionPlanItemActivity;
use App\Models\Production\ProductionPlanItem;
use App\Models\Production\ProductionQcCheck;
use Illuminate\Contracts\View\View;
use App\Services\Production\ProductionAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductionQcController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.qc.perform');
    }

    protected function resolveProject(Request $request, $project = null): ?Project
    {
        if ($project instanceof Project) {
            return $project;
        }

        $projectId = (int) $request->integer('project_id');
        if ($projectId <= 0) {
            return null;
        }

        return Project::query()->findOrFail($projectId);
    }

    protected function resolveProjectAndQc(Request $request, $project = null, $qc = null): array
    {
        // Project-scoped route: /projects/{project}/production-qc/{qc}
        if ($qc !== null) {
            $resolvedProject = $this->resolveProject($request, $project);
            if (! $resolvedProject) {
                throw new NotFoundHttpException('Project context missing.');
            }
            $resolvedQc = $qc instanceof ProductionQcCheck
                ? $qc
                : ProductionQcCheck::query()->findOrFail((int) $qc);

            return [$resolvedProject, $resolvedQc];
        }

        // Global route: /production/production-qc/{qc}
        $resolvedQc = $project instanceof ProductionQcCheck
            ? $project
            : ProductionQcCheck::query()->findOrFail((int) $project);

        return [Project::query()->findOrFail((int) $resolvedQc->project_id), $resolvedQc];
    }

    protected function activityNeedsTraceability($activity): bool
    {
        $code = strtoupper((string) ($activity->code ?? ''));
        $name = strtoupper((string) ($activity->name ?? ''));
        $isFitup = (bool) ($activity->is_fitupp ?? false);

        return $isFitup || str_contains($code, 'CUT') || str_contains($name, 'CUT');
    }

    public function index(Request $request, $project = null)
    {
        $project = $this->resolveProject($request, $project);
        if (! $project) {
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
                'moduleTitle' => 'QC Pending',
                'moduleDescription' => 'Select project to open pending quality checks.',
                'projects' => $projects,
                'q' => $q,
            ]);
        }

        $query = ProductionQcCheck::query()
            ->where('project_id', $project->id)
            ->where('result', 'pending')
            ->with(['plan', 'activity', 'planItemActivity.planItem']);

        $q = trim((string) request('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->whereHas('plan', function ($planQuery) use ($q) {
                    $planQuery->where('plan_number', 'like', '%' . $q . '%');
                })->orWhereHas('activity', function ($activityQuery) use ($q) {
                    $activityQuery->where('name', 'like', '%' . $q . '%')
                        ->orWhere('code', 'like', '%' . $q . '%');
                })->orWhereHas('planItemActivity.planItem', function ($itemQuery) use ($q) {
                    $itemQuery->where('item_code', 'like', '%' . $q . '%')
                        ->orWhere('assembly_mark', 'like', '%' . $q . '%');
                });
            });
        }

        if ($planId = (int) request('production_plan_id', 0)) {
            $query->where('production_plan_id', $planId);
        }

        if ($activityId = (int) request('production_activity_id', 0)) {
            $query->where('production_activity_id', $activityId);
        }

        $sort = (string) request('sort', '');
        $dir = strtolower((string) request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['id', 'production_plan_id', 'production_activity_id', 'created_at'];
        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy('id');
        }

        $perPage = (int) request('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $pending = $query->paginate($perPage)->withQueryString();

        $plans = DB::table('production_plans')
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get(['id', 'plan_number']);

        $activities = DB::table('production_activities')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('projects.production_qc.index', compact('project', 'pending', 'plans', 'activities'));
    }

    public function update(Request $request, $project = null, $qc = null)
    {
        [$project, $qc] = $this->resolveProjectAndQc($request, $project, $qc);

        if ((int)$qc->project_id !== (int)$project->id) abort(404);

        $data = $request->validate([
            'result' => ['required', 'in:passed,failed'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (
            $data['result'] === 'passed'
            && Schema::hasColumn('production_dpr_lines', 'traceability_done')
            && (int) ($qc->production_dpr_line_id ?? 0) > 0
        ) {
            $activity = DB::table('production_activities')
                ->where('id', (int) $qc->production_activity_id)
                ->first(['code', 'name', 'is_fitupp']);

            if ($activity && $this->activityNeedsTraceability($activity)) {
                $traceabilityDone = (int) DB::table('production_dpr_lines')
                    ->where('id', (int) $qc->production_dpr_line_id)
                    ->value('traceability_done');

                if ($traceabilityDone !== 1) {
                    return back()->with('error', 'Cannot pass QC until traceability is completed for this DPR line.');
                }
            }
        }

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
                    $pia->status = 'pending';
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
            $project->id,
            $event,
            'ProductionQcCheck',
            $qc->id,
            'QC updated',
            ['result' => $data['result']]
        );

        return back()->with('success', 'QC updated.');
    }
}
