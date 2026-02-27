<?php

namespace App\Http\Controllers\Production;

use App\Enums\BomItemMaterialCategory;
use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\Project;
use App\Models\Production\ProductionActivity;
use App\Models\Production\ProductionPlan;
use App\Models\Production\ProductionPlanItem;
use App\Models\Production\ProductionPlanItemActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductionPlanFromBomController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.create');
    }

    public function form(Request $request)
    {
        $projects = Project::query()
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $projectId = (int) ($request->route('project') ?? $request->integer('project_id') ?? 0);

        $boms = collect();

        if ($projectId > 0) {
            $boms = Bom::query()
                ->where('project_id', $projectId)
                ->whereIn('status', ['finalized', 'active'])
                ->orderByDesc('id')
                ->get();
        }

        return view('production.plans.from_bom', [
            'projects' => $projects,
            'projectId' => $projectId,
            'boms' => $boms,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
        ]);

        $project = Project::query()->findOrFail((int) $data['project_id']);
        $bom = Bom::query()->findOrFail((int) $data['bom_id']);

        if ((int) $bom->project_id !== (int) $project->id) {
            throw ValidationException::withMessages([
                'bom_id' => 'Selected BOM does not belong to selected project.',
            ]);
        }

        // BOM status may be enum or string
        $bomStatus = $bom->status;
        $bomStatusValue = $bomStatus instanceof \BackedEnum ? (string) $bomStatus->value : (string) $bomStatus;

        if (! in_array($bomStatusValue, ['finalized', 'active'], true)) {
            throw ValidationException::withMessages([
                'bom_id' => 'Only finalized/approved BOMs can be used for production planning.',
            ]);
        }

        // Detect production_plan_items columns safely (your DB differs across deployments)
        $ppiTable = 'production_plan_items';
        $hasCol = fn (string $col) => Schema::hasColumn($ppiTable, $col);

        // Helper: enum/string-safe material category value
        $matCatValue = static function ($v): ?string {
            if ($v instanceof \BackedEnum) {
                return (string) $v->value;
            }
            if ($v instanceof \UnitEnum) {
                return (string) $v->name;
            }

            return $v !== null ? (string) $v : null;
        };

        $plan = DB::transaction(function () use ($project, $bom, $hasCol, $matCatValue) {
            $plan = new ProductionPlan();
            $plan->project_id = $project->id;
            $plan->bom_id = $bom->id;

            $plan->plan_number = ProductionPlan::generateNumberForProject($project);

            $plan->status = 'draft';
            $plan->remarks = null;
            $plan->created_by = auth()->id();
            $plan->approved_by = null;
            $plan->approved_at = null;
            $plan->save();

            // Load all BOM items
            $bomItems = $bom->items()
                ->orderBy('level')
                ->orderBy('sequence_no')
                ->orderBy('id')
                ->get();

            // Build lookup map for parent traversal
            $byId = $bomItems->keyBy('id');

            // Identify "container assemblies" (assemblies that have sub-assemblies under them).
            // For these, routing is optional and we default all activities to disabled.
            $containerAssemblyIds = [];
            foreach ($bomItems as $it) {
                $cat = $matCatValue($it->material_category ?? null);
                if ($cat === BomItemMaterialCategory::FABRICATED_ASSEMBLY->value && ! empty($it->parent_item_id)) {
                    $containerAssemblyIds[(int) $it->parent_item_id] = true;
                }
            }

            $findAncestorAssembly = function ($item) use ($byId, $matCatValue) {
                $cur = $item;

                while ($cur && ! empty($cur->parent_item_id)) {
                    $parent = $byId->get((int) $cur->parent_item_id);
                    if (! $parent) {
                        break;
                    }

                    $cat = $matCatValue($parent->material_category ?? null);
                    if ($cat === BomItemMaterialCategory::FABRICATED_ASSEMBLY->value) {
                        return $parent;
                    }

                    $cur = $parent;
                }

                return null;
            };

            // Active activities once (performance)
            $activities = ProductionActivity::query()
                ->where('is_active', true)
                ->orderBy('default_sequence')
                ->get();

            foreach ($bomItems as $bi) {
                $biCat = $matCatValue($bi->material_category ?? null);

                // Determine if this BOM row is assembly or part
                $isAssembly = $biCat === BomItemMaterialCategory::FABRICATED_ASSEMBLY->value;

                // Container assembly = an assembly that contains at least one child assembly
                $isContainerAssembly = $isAssembly && isset($containerAssemblyIds[(int) ($bi->id ?? 0)]);

                // Compute assembly context
                $ancestorAssembly = null;
                $assemblyMark = null;
                $assemblyType = null;

                if ($isAssembly) {
                    // Assembly item itself: mark = its own item_code
                    $assemblyMark = $bi->item_code ?? null;
                    $assemblyType = $bi->assembly_type ?? null;
                } else {
                    // Part item: find nearest parent assembly
                    $ancestorAssembly = $findAncestorAssembly($bi);
                    if ($ancestorAssembly) {
                        $assemblyMark = $ancestorAssembly->item_code ?? null;
                        $assemblyType = $ancestorAssembly->assembly_type ?? null;
                    }
                }

                // Planned qty/weight from BOM fields
                $qty = (float) ($bi->quantity ?? 0);
                if ($qty < 0) {
                    $qty = 0;
                }

                $totalW = $bi->total_weight ?? null;
                $unitW = $bi->unit_weight ?? null;

                $plannedWeight = null;
                if ($totalW !== null && $totalW !== '') {
                    $plannedWeight = (float) $totalW;
                } elseif ($unitW !== null && $unitW !== '') {
                    $plannedWeight = $qty * (float) $unitW;
                }

                $pi = new ProductionPlanItem();

                $pi->production_plan_id = $plan->id;
                $pi->bom_item_id = $bi->id ?? null;

                if ($hasCol('item_type')) {
                    $pi->item_type = $isAssembly ? 'assembly' : 'part';
                }

                $pi->item_code = $bi->item_code ?? null;
                $pi->description = $bi->description ?? null;

                if ($hasCol('grade')) {
                    $pi->grade = $bi->grade ?? null;
                }

                if ($hasCol('assembly_mark')) {
                    $pi->assembly_mark = $assemblyMark;
                }

                if ($hasCol('assembly_type')) {
                    $pi->assembly_type = $assemblyType;
                }

                if ($hasCol('level')) {
                    $pi->level = (int) ($bi->level ?? 0);
                }

                if ($hasCol('sequence_no')) {
                    $pi->sequence_no = (int) ($bi->sequence_no ?? 0);
                }

                if ($hasCol('uom_id')) {
                    $pi->uom_id = $bi->uom_id ?? null;
                }

                if ($hasCol('planned_qty')) {
                    $pi->planned_qty = $qty;
                }

                if ($hasCol('planned_weight_kg')) {
                    $pi->planned_weight_kg = $plannedWeight !== null ? (float) $plannedWeight : null;
                }

                // KPI metrics (optional columns)
                if ($hasCol('unit_area_m2')) {
                    $pi->unit_area_m2 = $bi->unit_area_m2 ?? null;
                }

                if ($hasCol('unit_cut_length_m')) {
                    $pi->unit_cut_length_m = $bi->unit_cut_length_m ?? null;
                }

                if ($hasCol('unit_weld_length_m')) {
                    $pi->unit_weld_length_m = $bi->unit_weld_length_m ?? null;
                }

                if ($hasCol('status')) {
                    $pi->status = 'pending';
                }

                $pi->save();

                // Create activity route rows for this plan item.
                // NOTE: Container assemblies default to all activities disabled.
                $created = 0;

                foreach ($activities as $act) {
                    $applies = (string) ($act->applies_to ?? 'both'); // part|assembly|both

                    $ok = false;
                    if ($applies === 'both') {
                        $ok = true;
                    }
                    if ($applies === 'part' && ! $isAssembly) {
                        $ok = true;
                    }
                    if ($applies === 'assembly' && $isAssembly) {
                        $ok = true;
                    }

                    if (! $ok) {
                        continue;
                    }

                    ProductionPlanItemActivity::create([
                        'production_plan_item_id' => $pi->id,
                        'production_activity_id' => $act->id,
                        'sequence_no' => (int) ($act->default_sequence ?? 0),
                        'is_enabled' => ! $isContainerAssembly,

                        'contractor_party_id' => null,
                        'worker_user_id' => null,
                        'rate' => 0,
                        'rate_uom_id' => null,
                        'planned_date' => null,

                        'status' => 'pending',
                        'qc_status' => 'na',
                    ]);

                    $created++;
                }

                // Fallback: if nothing matched, enable first activity to avoid approve-block
                if (! $isContainerAssembly && $created === 0 && $activities->count() > 0) {
                    $act = $activities->first();

                    ProductionPlanItemActivity::create([
                        'production_plan_item_id' => $pi->id,
                        'production_activity_id' => $act->id,
                        'sequence_no' => (int) ($act->default_sequence ?? 0),
                        'is_enabled' => true,

                        'contractor_party_id' => null,
                        'worker_user_id' => null,
                        'rate' => 0,
                        'rate_uom_id' => null,
                        'planned_date' => null,

                        'status' => 'pending',
                        'qc_status' => 'na',
                    ]);
                }
            }

            return $plan;
        });

        return redirect()
            ->route('projects.production-plans.show', [
                'project' => $project->id,
                'production_plan' => $plan->id,
            ])
            ->with('success', 'Production Plan created successfully from BOM.');
    }
}
