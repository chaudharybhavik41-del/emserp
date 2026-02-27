<?php

namespace App\Http\Controllers;

use App\Enums\BomItemMaterialCategory;
use App\Models\Bom;
use App\Models\Project;
use App\Models\SectionPlan;
use App\Models\SectionPlanBar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionPlanController extends Controller
{
    public function index(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        $requirements = $this->buildSectionRequirements($bom);

        // Attach planned length per group
        foreach ($requirements as &$group) {
            $plan = SectionPlan::query()
                ->where('project_id', $project->id)
                ->where('bom_id', $bom->id)
                ->where('section_profile', $group['section_profile'])
                ->where('grade', $group['grade'])
                ->first();

            $plannedLengthMm = 0;
            if ($plan) {
                $plannedLengthMm = (int) $plan->bars
                    ->map(fn (SectionPlanBar $bar) => (int) $bar->length_mm * (int) $bar->quantity)
                    ->sum();
            }

            $group['planned_length_mm']    = $plannedLengthMm;
            $group['planned_length_m']     = round($plannedLengthMm / 1000, 3);
            $group['required_length_m']    = round($group['total_length_mm'] / 1000, 3);
            $group['remaining_length_m']   = round(
                max(0, $group['total_length_mm'] - $plannedLengthMm) / 1000,
                3
            );
            $group['section_plan']         = $plan;
        }
        unset($group);

        return view('projects.boms.section_plans.index', [
            'project'      => $project,
            'bom'          => $bom,
            'requirements' => $requirements,
        ]);
    }

    public function edit(Project $project, Bom $bom, Request $request): View
    {
        $this->ensureProjectBom($project, $bom);

        $sectionProfile = trim((string) $request->input('section_profile', ''));
        $grade          = trim((string) $request->input('grade', ''));

        if ($sectionProfile === '' || $grade === '') {
            abort(404, 'Section profile and grade are required.');
        }

        $requirements = $this->buildSectionRequirements($bom);

        $targetGroup = null;
        foreach ($requirements as $group) {
            if (
                trim((string) $group['section_profile']) === $sectionProfile
                && trim((string) $group['grade']) === $grade
            ) {
                $targetGroup = $group;
                break;
            }
        }

        if (! $targetGroup) {
            abort(404, 'Section requirement group not found.');
        }

        // Find or create SectionPlan for this group
        $plan = SectionPlan::query()->firstOrCreate(
            [
                'project_id'      => $project->id,
                'bom_id'          => $bom->id,
                'section_profile' => $targetGroup['section_profile'],
                'grade'           => $targetGroup['grade'],
            ],
            [
                'item_id' => $targetGroup['default_item_id'] ?? null,
                'name'    => sprintf(
                    '%s - %s (%s)',
                    $targetGroup['section_profile'],
                    $targetGroup['grade'],
                    $bom->bom_number ?? ('BOM-' . $bom->id)
                ),
            ]
        );

        $plan->load('bars');

        $requiredLengthMm  = (int) $targetGroup['total_length_mm'];
        $plannedLengthMm   = (int) $plan->bars
            ->map(fn (SectionPlanBar $bar) => (int) $bar->length_mm * (int) $bar->quantity)
            ->sum();
        $remainingLengthMm = max(0, $requiredLengthMm - $plannedLengthMm);

        return view('projects.boms.section_plans.edit', [
            'project'            => $project,
            'bom'                => $bom,
            'plan'               => $plan,
            'group'              => $targetGroup,
            'requiredLengthMm'   => $requiredLengthMm,
            'plannedLengthMm'    => $plannedLengthMm,
            'remainingLengthMm'  => $remainingLengthMm,
        ]);
    }

    public function storeBar(Project $project, Bom $bom, SectionPlan $sectionPlan, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);
        $this->ensurePlanBelongsTo($sectionPlan, $project, $bom);

        $data = $request->validate([
            'length_mm' => ['required', 'integer', 'min:1'],
            'quantity'  => ['required', 'integer', 'min:1'],
            'remarks'   => ['nullable', 'string', 'max:255'],
        ]);

        SectionPlanBar::create([
            'section_plan_id' => $sectionPlan->id,
            'length_mm'       => $data['length_mm'],
            'quantity'        => $data['quantity'],
            'remarks'         => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Planned bar added.');
    }

    public function destroyBar(Project $project, Bom $bom, SectionPlan $sectionPlan, SectionPlanBar $bar): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);
        $this->ensurePlanBelongsTo($sectionPlan, $project, $bom);

        if ($bar->section_plan_id !== $sectionPlan->id) {
            abort(404);
        }

        $bar->delete();

        return back()->with('success', 'Planned bar deleted.');
    }

    /**
     * Build section requirements grouped by section profile + grade.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildSectionRequirements(Bom $bom): array
    {
        $bom->loadMissing([
            'items' => function ($q) {
                $q->orderBy('level')->orderBy('sequence_no');
            },
            'items.item',
            'items.uom',
        ]);

        $items = $bom->items;


    // Map BOM items by id for effective qty calculations (avoids N+1)
    $itemsById = $items->keyBy('id')->all();

        $groups = [];

        foreach ($items as $bomItem) {
            if (! method_exists($bomItem, 'isLeafMaterial') || ! $bomItem->isLeafMaterial()) {
                continue;
            }

            $categoryEnum = $bomItem->material_category;
            $category     = $categoryEnum?->value;

            if ($category !== BomItemMaterialCategory::STEEL_SECTION->value) {
                continue;
            }

            $grade    = $bomItem->grade;
            $dims     = $bomItem->dimensions ?? [];
            $qty = method_exists($bomItem, 'effectiveQuantity') ? (float) $bomItem->effectiveQuantity($itemsById) : (float) ($bomItem->quantity ?? 0);
            $uomCode  = $bomItem->uom?->code ?? null;
            $itemId   = $bomItem->item_id;
            $itemCode = $bomItem->item?->code ?? null;
            $itemName = $bomItem->item?->name ?? null;

            $sectionProfile = $dims['section'] ?? ($bomItem->item->name ?? $bomItem->item->code ?? null);
            $sectionProfile = $sectionProfile ? trim($sectionProfile) : 'UNKNOWN';

            $lengthMm = isset($dims['length_mm']) ? (float) $dims['length_mm'] : 0.0;
            $lineTotalLengthMm = $lengthMm * $qty;

            $key = implode('|', [
                $sectionProfile,
                $grade ?? 'unknown',
            ]);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'section_profile'   => $sectionProfile,
                    'grade'             => $grade,
                    'uom'               => $uomCode,
                    'total_length_mm'   => 0.0,
                    'total_length_m'    => 0.0,
                    'lines'             => [],
                    'default_item_id'   => null,
                    'default_item_code' => null,
                    'default_item_name' => null,
                ];
            }

            $groups[$key]['total_length_mm'] += $lineTotalLengthMm;
            $groups[$key]['total_length_m']   = round($groups[$key]['total_length_mm'] / 1000, 3);

            $groups[$key]['lines'][] = [
                'bom_item_id'        => $bomItem->id,
                'item_code'          => $itemCode,
                'item_name'          => $itemName,
                'description'        => $bomItem->description,
                'level'              => $bomItem->level,
                'sequence_no'        => $bomItem->sequence_no,
                'length_mm'          => $lengthMm,
                'quantity'           => $qty,
                'line_total_length_m'=> round($lineTotalLengthMm / 1000, 3),
            ];

            if ($itemId && ! $groups[$key]['default_item_id']) {
                $groups[$key]['default_item_id']   = $itemId;
                $groups[$key]['default_item_code'] = $itemCode;
                $groups[$key]['default_item_name'] = $itemName;
            }
        }

        $requirements = collect($groups)
            ->sortBy([
                fn ($g) => $g['section_profile'] ?? '',
                fn ($g) => $g['grade'] ?? '',
            ])
            ->values()
            ->all();

        return $requirements;
    }

    protected function ensureProjectBom(Project $project, Bom $bom): void
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }
    }

    protected function ensurePlanBelongsTo(SectionPlan $plan, Project $project, Bom $bom): void
    {
        if ($plan->project_id !== $project->id || $plan->bom_id !== $bom->id) {
            abort(404);
        }
    }
}