<?php

namespace App\Http\Controllers;

use App\Enums\BomItemMaterialCategory;
use App\Models\Bom;
use App\Models\MaterialStockPiece;
use App\Models\Project;
use Illuminate\View\View;

class BomPurchaseController extends Controller
{
    /**
     * Plate purchase summary for a BOM, driven by Cutting Plans / planned stock.
     *
     * Shows grouped planned plates (grade + thk + size) based on MaterialStockPiece
     * rows that are:
     *  - material_category = STEEL_PLATE
     *  - source_type       = 'planned'
     *  - reserved_for_project_id = project
     *  - reserved_for_bom_id     = bom
     */
    public function plates(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        $pieces = MaterialStockPiece::query()
            ->with('item')
            ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
            ->where('reserved_for_project_id', $project->id)
            ->where('reserved_for_bom_id', $bom->id)
            ->where('source_type', 'planned')
            ->orderBy('thickness_mm')
            ->orderBy('width_mm')
            ->orderBy('length_mm')
            ->get();

        $groups = [];

        foreach ($pieces as $piece) {
            $item   = $piece->item;
            $grade  = $item->grade ?? null;
            $code   = $item->code ?? null;
            $name   = $item->name ?? null;

            $thk    = (int) ($piece->thickness_mm ?? 0);
            $width  = (int) ($piece->width_mm ?? 0);
            $length = (int) ($piece->length_mm ?? 0);

            $key = implode('|', [
                $grade ?? '',
                $thk,
                $width,
                $length,
            ]);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'grade'           => $grade,
                    'item_code'       => $code,
                    'item_name'       => $name,
                    'thickness_mm'    => $thk,
                    'width_mm'        => $width,
                    'length_mm'       => $length,
                    'count'           => 0,
                    'total_weight_kg' => 0.0,
                    'pieces'          => [],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['total_weight_kg'] += (float) ($piece->weight_kg ?? 0);
            $groups[$key]['pieces'][] = $piece;
        }

        // Sort groups by grade, thickness, width, length
        $groups = collect($groups)->sortBy([
            ['grade', 'asc'],
            ['thickness_mm', 'asc'],
            ['width_mm', 'asc'],
            ['length_mm', 'asc'],
        ])->values();

        return view('projects.boms.purchase.plates', [
            'project'        => $project,
            'bom'            => $bom,
            'groupedPlates'  => $groups,
            'totalPlates'    => $pieces->count(),
            'totalWeightPlanned' => $pieces->sum(function ($p) {
                return (float) ($p->weight_kg ?? 0);
            }),
        ]);
    }

    protected function ensureProjectBom(Project $project, Bom $bom): void
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }
    }
}
