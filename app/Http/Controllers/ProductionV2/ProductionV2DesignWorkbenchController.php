<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyPartRequirement;
use App\Models\ProductionV2\ProductionCuttingPlan;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Support\ProductionV2\RevisionImpactAnalyzer;
use App\Support\ProductionV2\RevisionDraftBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionV2DesignWorkbenchController extends Controller
{
    public function __construct(
        protected RevisionImpactAnalyzer $revisionImpactAnalyzer,
        protected RevisionDraftBuilder $revisionDraftBuilder
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.view|production.plan.create|production.plan.update')->only(['show']);
        $this->middleware('permission:production.plan.update')->only(['batchCorrect']);
    }

    public function show(Project $project)
    {
        $summary = [
            'part_definitions' => ProductionPartDefinition::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'obsolete'])->count(),
            'assemblies' => ProductionAssembly::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'closed'])->count(),
            'requirements' => ProductionAssemblyPartRequirement::query()
                ->whereHas('assembly', fn ($query) => $query->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'closed']))
                ->count(),
            'cutting_plans' => ProductionCuttingPlan::query()->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'cancelled'])->count(),
        ];

        $plannedCuts = DB::table('production_v2_cutting_plan_allocations as cpa')
            ->join('production_v2_cutting_plans as cp', 'cp.id', '=', 'cpa.cutting_plan_id')
            ->where('cp.project_id', $project->id)
            ->whereNotIn('cp.status', ['superseded', 'cancelled'])
            ->select('cpa.part_definition_id', DB::raw('SUM(cpa.planned_qty) as planned_cut_qty'))
            ->groupBy('cpa.part_definition_id');

        $materialPlanningRows = DB::table('production_v2_part_definitions as pd')
            ->leftJoinSub($plannedCuts, 'pc', 'pc.part_definition_id', '=', 'pd.id')
            ->leftJoin('uoms as u', 'u.id', '=', 'pd.uom_id')
            ->where('pd.project_id', $project->id)
            ->whereNotIn('pd.status', ['superseded', 'obsolete'])
            ->orderBy('pd.part_code')
            ->get([
                'pd.id',
                'pd.part_code',
                'pd.part_name',
                'pd.part_type',
                'pd.required_qty',
                'u.code as uom_code',
                DB::raw('COALESCE(pc.planned_cut_qty, 0) as planned_cut_qty'),
                DB::raw('(pd.required_qty - COALESCE(pc.planned_cut_qty, 0)) as planning_gap_qty'),
            ]);

        $planningGapRows = collect($materialPlanningRows)
            ->filter(fn ($row) => (float) $row->planning_gap_qty > 0.0001)
            ->sortByDesc(fn ($row) => (float) $row->planning_gap_qty)
            ->take(8)
            ->values();

        $latestParts = ProductionPartDefinition::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', ['superseded', 'obsolete'])
            ->latest('id')
            ->limit(6)
            ->get();

        $latestAssemblies = ProductionAssembly::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', ['superseded', 'closed'])
            ->withCount('requirements')
            ->latest('id')
            ->limit(6)
            ->get();

        $latestCuttingPlans = ProductionCuttingPlan::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', ['superseded', 'cancelled'])
            ->withCount('allocations')
            ->latest('plan_date')
            ->latest('id')
            ->limit(6)
            ->get();

        return view('production_v2.design_workbench', [
            'project' => $project,
            'summary' => $summary,
            'planningGapRows' => $planningGapRows,
            'latestParts' => $latestParts,
            'latestAssemblies' => $latestAssemblies,
            'latestCuttingPlans' => $latestCuttingPlans,
            'revisionImpact' => $this->revisionImpactAnalyzer->projectSummary($project),
        ]);
    }

    public function batchCorrect(Request $request, Project $project)
    {
        $data = $request->validate([
            'assembly_ids' => ['nullable', 'array'],
            'assembly_ids.*' => ['integer'],
            'cutting_plan_ids' => ['nullable', 'array'],
            'cutting_plan_ids.*' => ['integer'],
            'material_requirement_ids' => ['nullable', 'array'],
            'material_requirement_ids.*' => ['integer'],
        ]);

        $assemblyIds = collect($data['assembly_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $cuttingPlanIds = collect($data['cutting_plan_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $materialRequirementIds = collect($data['material_requirement_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($assemblyIds->isEmpty() && $cuttingPlanIds->isEmpty() && $materialRequirementIds->isEmpty()) {
            throw ValidationException::withMessages([
                'assembly_ids' => 'Select at least one impacted design record to correct.',
            ]);
        }

        $createdAssemblies = collect();
        $createdPlans = collect();
        $createdRequirements = collect();
        $remaps = collect();
        $errors = [];

        if ($assemblyIds->isNotEmpty()) {
            $assemblies = ProductionAssembly::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $assemblyIds)
                ->get();

            foreach ($assemblies as $assembly) {
                if (! in_array($assembly->status, ['released', 'superseded'], true)) {
                    $errors[] = 'Assembly ' . $assembly->assembly_code . ' is not in a revisable state.';
                    continue;
                }

                $result = $this->revisionDraftBuilder->createAssemblyRevisionWithLatestParts($assembly, (int) auth()->id());
                $createdAssemblies->push($result['revision']);
                foreach ($result['auto_replaced'] as $label) {
                    $remaps->push('Assembly ' . $assembly->assembly_code . ': ' . $label);
                }
            }
        }

        if ($cuttingPlanIds->isNotEmpty()) {
            $cuttingPlans = ProductionCuttingPlan::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $cuttingPlanIds)
                ->get();

            foreach ($cuttingPlans as $cuttingPlan) {
                if (! in_array($cuttingPlan->status, ['released', 'superseded'], true)) {
                    $errors[] = 'Cutting plan ' . $cuttingPlan->plan_number . ' is not in a revisable state.';
                    continue;
                }

                $result = $this->revisionDraftBuilder->createCuttingPlanRevisionWithLatestParts($cuttingPlan, (int) auth()->id());
                $createdPlans->push($result['revision']);
                foreach ($result['auto_replaced'] as $label) {
                    $remaps->push('Cutting plan ' . $cuttingPlan->plan_number . ': ' . $label);
                }
            }
        }

        if ($materialRequirementIds->isNotEmpty()) {
            $materialRequirements = \App\Models\ProductionV2\ProductionMaterialRequirement::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $materialRequirementIds)
                ->get();

            foreach ($materialRequirements as $materialRequirement) {
                if (! in_array($materialRequirement->status, ['released', 'superseded'], true)) {
                    $errors[] = 'Material requirement ' . $materialRequirement->requirement_number . ' is not in a revisable state.';
                    continue;
                }

                $result = $this->revisionDraftBuilder->createMaterialRequirementRevision($materialRequirement, (int) auth()->id());
                $createdRequirements->push($result['revision']);
                if ($result['refreshed']) {
                    $remaps->push('Material requirement ' . $materialRequirement->requirement_number . ': refreshed released-design snapshot');
                }
            }
        }

        if ($createdAssemblies->isEmpty() && $createdPlans->isEmpty() && $createdRequirements->isEmpty()) {
            throw ValidationException::withMessages([
                'assembly_ids' => implode(' ', $errors),
            ]);
        }

        $message = 'Created ' . $createdAssemblies->count() . ' assembly draft(s), ' . $createdPlans->count() . ' cutting-plan draft(s), and ' . $createdRequirements->count() . ' material-requirement draft(s).';
        if ($remaps->isNotEmpty()) {
            $message .= ' Auto-updated references: ' . $remaps->unique()->implode('; ') . '.';
        }
        if (! empty($errors)) {
            $message .= ' Skipped: ' . implode(' ', $errors);
        }

        return redirect()
            ->route('production-v2.project.design', ['project' => $project->id])
            ->with('success', $message);
    }
}
