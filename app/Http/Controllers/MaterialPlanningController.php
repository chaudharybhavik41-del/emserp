<?php

namespace App\Http\Controllers;

use App\Enums\BomItemMaterialCategory;
use App\Enums\MaterialStockPieceStatus;
use App\Models\Bom;
use App\Models\Item;
use App\Models\MaterialStockPiece;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialPlanningController extends Controller
{
    public function index(Project $project, Bom $bom): View
    {
        $this->ensureProjectBom($project, $bom);

        $rawRequirements = $this->buildRawRequirements($bom);

        // Attach reserved coverage per group
        foreach ($rawRequirements as &$group) {
            $reservedQuery = MaterialStockPiece::query()
                ->where('reserved_for_project_id', $project->id)
                ->where('reserved_for_bom_id', $bom->id)
                ->where('status', MaterialStockPieceStatus::AVAILABLE->value);

            if ($group['category'] === 'plate') {
                $reservedQuery
                    ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                    ->where('thickness_mm', (int) $group['thickness_mm'])
                    ->whereHas('item', function ($q) use ($group) {
                        if ($group['grade']) {
                            $q->where('grade', $group['grade']);
                        }
                    });
            } elseif ($group['category'] === 'section') {
                $reservedQuery
                    ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
                    ->where('section_profile', $group['section'])
                    ->whereHas('item', function ($q) use ($group) {
                        if ($group['grade']) {
                            $q->where('grade', $group['grade']);
                        }
                    });
            }

            $reservedPieces = $reservedQuery->get();

            $group['reserved_pieces_count'] = $reservedPieces->count();
            $group['reserved_weight_kg']    = (float) $reservedPieces->sum('weight_kg');
            $group['remaining_weight_kg']   = max(
                0,
                (float) $group['total_weight'] - (float) $group['reserved_weight_kg']
            );
        }
        unset($group);

        return view('projects.boms.material_planning.index', [
            'project'         => $project,
            'bom'             => $bom,
            'rawRequirements' => $rawRequirements,
        ]);
    }

    public function selectStock(Project $project, Bom $bom, Request $request): View
    {
        $this->ensureProjectBom($project, $bom);

        // Build requirements and read filters
        $rawRequirements = $this->buildRawRequirements($bom);

        $category = $request->string('group_category')->toString();
        $grade    = trim($request->string('grade')->toString());
        $thk      = $request->input('thickness_mm');
        $section  = trim($request->string('section_profile')->toString());

        // Locate the matching group
        $targetGroup = null;

        foreach ($rawRequirements as $group) {
            if (($group['category'] ?? null) !== $category) {
                continue;
            }

            $groupGrade = trim((string) ($group['grade'] ?? ''));

            if ($groupGrade !== $grade) {
                continue;
            }

            if ($category === 'plate') {
                if ((float) ($group['thickness_mm'] ?? 0) !== (float) $thk) {
                    continue;
                }
            }

            if ($category === 'section') {
                $groupSection = trim((string) ($group['section'] ?? ''));
                if ($groupSection !== $section) {
                    continue;
                }
            }

            $targetGroup = $group;
            break;
        }

        if (! $targetGroup) {
            abort(404, 'Material planning group not found for the given filters.');
        }

        // Already reserved for this BOM / project
        $reservedQuery = MaterialStockPiece::query()
            ->with('item')
            ->where('reserved_for_project_id', $project->id)
            ->where('reserved_for_bom_id', $bom->id);

        // Available pieces that match group (grade + thickness/section)
        $availableQuery = MaterialStockPiece::query()
            ->with('item')
            ->whereNull('reserved_for_project_id')
            ->whereNull('reserved_for_bom_id');

        if ($category === 'plate') {
            $reservedQuery
                ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                ->where('thickness_mm', (int) $targetGroup['thickness_mm'])
                ->whereHas('item', function ($q) use ($targetGroup) {
                    if (! empty($targetGroup['grade'])) {
                        $q->where('grade', $targetGroup['grade']);
                    }
                });

            $availableQuery
                ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                ->where('thickness_mm', (int) $targetGroup['thickness_mm'])
                ->whereHas('item', function ($q) use ($targetGroup) {
                    if (! empty($targetGroup['grade'])) {
                        $q->where('grade', $targetGroup['grade']);
                    }
                });
        } else { // section
            $reservedQuery
                ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
                ->where('section_profile', $targetGroup['section'])
                ->whereHas('item', function ($q) use ($targetGroup) {
                    if (! empty($targetGroup['grade'])) {
                        $q->where('grade', $targetGroup['grade']);
                    }
                });

            $availableQuery
                ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
                ->where('section_profile', $targetGroup['section'])
                ->whereHas('item', function ($q) use ($targetGroup) {
                    if (! empty($targetGroup['grade'])) {
                        $q->where('grade', $targetGroup['grade']);
                    }
                });
        }

        $reservedPieces  = $reservedQuery->orderBy('id')->get();
        $availablePieces = $availableQuery->orderBy('id')->limit(200)->get();

        $reservedWeight  = (float) $reservedPieces->sum('weight_kg');
        $remainingWeight = max(0, (float) $targetGroup['total_weight'] - $reservedWeight);

        return view('projects.boms.material_planning.select_stock', [
            'project'         => $project,
            'bom'             => $bom,
            'group'           => $targetGroup,
            'reservedPieces'  => $reservedPieces,
            'availablePieces' => $availablePieces,
            'reservedWeight'  => $reservedWeight,
            'remainingWeight' => $remainingWeight,
        ]);
    }

    public function allocate(Project $project, Bom $bom, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        $category = $request->string('group_category')->toString();
        $grade    = trim($request->string('grade')->toString());
        $thk      = $request->input('thickness_mm');
        $section  = trim($request->string('section_profile')->toString());
        $action   = $request->string('action')->toString() ?: 'reserve';

        if (! in_array($category, ['plate', 'section'], true)) {
            abort(404);
        }

        $ids = $request->input('stock_piece_ids', []);
        if (! is_array($ids) || empty($ids)) {
            return back()->with('warning', 'No stock pieces selected.');
        }

        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            return back()->with('warning', 'No valid stock pieces selected.');
        }

        $baseQuery = MaterialStockPiece::query()
            ->whereIn('id', $ids);

        if ($category === 'plate') {
            $baseQuery
                ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                ->where('thickness_mm', (int) $thk)
                ->whereHas('item', function ($q) use ($grade) {
                    if ($grade) {
                        $q->where('grade', $grade);
                    }
                });
        } else {
            $baseQuery
                ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
                ->where('section_profile', $section)
                ->whereHas('item', function ($q) use ($grade) {
                    if ($grade) {
                        $q->where('grade', $grade);
                    }
                });
        }

        if ($action === 'reserve') {
            // Only reserve pieces that are not yet reserved
            $affected = (clone $baseQuery)
                ->whereNull('reserved_for_project_id')
                ->whereNull('reserved_for_bom_id')
                ->update([
                    'reserved_for_project_id' => $project->id,
                    'reserved_for_bom_id'     => $bom->id,
                ]);

            $message = $affected > 0
                ? "Reserved {$affected} stock piece(s) for this BOM."
                : 'No new pieces were reserved (they may already be reserved).';
        } elseif ($action === 'release') {
            // Release only pieces reserved for this BOM
            $affected = (clone $baseQuery)
                ->where('reserved_for_project_id', $project->id)
                ->where('reserved_for_bom_id', $bom->id)
                ->update([
                    'reserved_for_project_id' => null,
                    'reserved_for_bom_id'     => null,
                ]);

            $message = $affected > 0
                ? "Released {$affected} stock piece(s) from this BOM."
                : 'No pieces were released.';
        } else {
            abort(400, 'Invalid action.');
        }

        return redirect()
            ->route('projects.boms.material-planning.select-stock', [
                $project,
                $bom,
                'group_category'  => $category,
                'grade'           => $grade,
                'thickness_mm'    => $thk,
                'section_profile' => $section,
            ])
            ->with('success', $message);
    }

    public function addPlannedPiece(Project $project, Bom $bom, Request $request): RedirectResponse
    {
        $this->ensureProjectBom($project, $bom);

        $category = $request->string('group_category')->toString();
        $grade    = trim($request->string('grade')->toString());
        $thk      = $request->input('thickness_mm');

        $section  = trim($request->string('section_profile')->toString());
        $section  = $section !== '' ? $section : null;

        if (! in_array($category, ['plate', 'section'], true)) {
            abort(404);
        }

        // Rebuild groups so we can verify this group and fetch default item
        $rawRequirements = $this->buildRawRequirements($bom);

        $targetGroup = null;

        foreach ($rawRequirements as $group) {
            if (($group['category'] ?? null) !== $category) {
                continue;
            }

            if (trim((string) ($group['grade'] ?? '')) !== $grade) {
                continue;
            }

            if ($category === 'plate') {
                if ((float) ($group['thickness_mm'] ?? 0) !== (float) $thk) {
                    continue;
                }
            }

            if ($category === 'section') {
                $groupSection = trim((string) ($group['section'] ?? ''));
                if ($groupSection !== ($section ?? '')) {
                    continue;
                }
            }

            $targetGroup = $group;
            break;
        }

        if (! $targetGroup) {
            abort(404, 'Material planning group not found for planned piece.');
        }

        // Item that represents the RAW material for this group
        $itemId = $targetGroup['default_item_id'] ?? null;

        if (! $itemId) {
            return back()->with(
                'warning',
                'No default RAW item found for this group. Please link BOM items to an Item first.'
            );
        }

        /** @var Item|null $item */
        $item = Item::query()->find($itemId);
        if (! $item) {
            return back()->with('warning', 'Item not found for this group.');
        }

        $data = $request->validate([
            'planned_quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            // For plates this is width; for sections we ignore width and only use length
            'width_mm'         => ['nullable', 'integer', 'min:1'],
            'length_mm'        => ['required', 'integer', 'min:1'],
            'location'         => ['nullable', 'string', 'max:100'],
            'remarks'          => ['nullable', 'string'],
        ]);

        $qty       = (int) $data['planned_quantity'];
        $widthMm   = $data['width_mm'] ?? null;
        $lengthMm  = (int) $data['length_mm'];
        $location  = $data['location'] ?? null;
        $remarks   = $data['remarks'] ?? null;

        $createdCount = 0;

        for ($i = 0; $i < $qty; $i++) {
            $pieceData = [
                'item_id'                 => $item->id,

                'material_category'       => $category === 'plate'
                    ? BomItemMaterialCategory::STEEL_PLATE->value
                    : BomItemMaterialCategory::STEEL_SECTION->value,

                'thickness_mm'            => $category === 'plate' ? (int) $thk : null,
                'width_mm'                => $category === 'plate' ? $widthMm : null,
                'length_mm'               => $lengthMm,

                'section_profile'         => $category === 'section' ? $section : null,

                'weight_kg'               => null,
                'plate_number'            => null,
                'heat_number'             => null,
                'mtc_number'              => null,
                'origin_project_id'       => $project->id,
                'origin_bom_id'           => $bom->id,
                'mother_piece_id'         => null,

                'status'                  => MaterialStockPieceStatus::AVAILABLE,

                'reserved_for_project_id' => $project->id,
                'reserved_for_bom_id'     => $bom->id,

                'source_type'             => 'planned',
                'source_reference'        => 'BOM:' . ($bom->bom_number ?? $bom->id),
                'location'                => $location,
                'remarks'                 => $remarks,
            ];

            // Auto-calc weight for plates
            if ($category === 'plate' && $widthMm && $thk && $lengthMm) {
                $density = $item->density ?: 7850;
                $volumeM3 = ($thk / 1000) * ($widthMm / 1000) * ($lengthMm / 1000);
                $pieceData['weight_kg'] = round($volumeM3 * $density, 3);
            }

            // Auto-calc weight for sections using weight_per_meter
            if ($category === 'section' && $lengthMm && $item->weight_per_meter) {
                $lengthM = $lengthMm / 1000;
                $pieceData['weight_kg'] = round($item->weight_per_meter * $lengthM, 3);
            }

            MaterialStockPiece::create($pieceData);
            $createdCount++;
        }

        return redirect()
            ->route('projects.boms.material-planning.select-stock', [
                $project,
                $bom,
                'group_category'  => $category,
                'grade'           => $grade,
                'thickness_mm'    => $thk,
                'section_profile' => $section,
            ])
            ->with('success', "Planned {$createdCount} new stock piece(s) for this BOM.");
    }

    /**
     * Build raw material requirements (plates + sections) from BOM.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildRawRequirements(Bom $bom): array
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

        $raw = [];

        foreach ($items as $item) {
            if (! method_exists($item, 'isLeafMaterial') || ! $item->isLeafMaterial()) {
                continue;
            }

            $categoryEnum = $item->material_category;
            $category     = $categoryEnum?->value;

            if (! $category) {
                continue;
            }

            if (! in_array($category, [
                BomItemMaterialCategory::STEEL_PLATE->value,
                BomItemMaterialCategory::STEEL_SECTION->value,
            ], true)) {
                continue;
            }

            // Grade is already coming from Item master via accessor
            $grade        = $item->grade;
            $dims         = $item->dimensions ?? [];
            $qty = method_exists($item, 'effectiveQuantity') ? (float) $item->effectiveQuantity($itemsById) : (float) ($item->quantity ?? 0);
            $totalWeight = method_exists($item, 'effectiveTotalWeight') ? (float) $item->effectiveTotalWeight($itemsById) : (float) ($item->total_weight ?? 0);
            $uomCode      = $item->uom?->code ?? null;
            $itemId       = $item->item_id;
            $itemCode     = $item->item?->code ?? null;
            $itemName     = $item->item?->name ?? null;

            if ($category === BomItemMaterialCategory::STEEL_PLATE->value) {
                $thickness = isset($dims['thickness_mm'])
                    ? (float) $dims['thickness_mm']
                    : null;

                $key = implode('|', [
                    'plate',
                    $grade ?? 'unknown',
                    $thickness ?? 'na',
                ]);

                if (! isset($raw[$key])) {
                    $raw[$key] = [
                        'category'          => 'plate',
                        'grade'             => $grade,
                        'thickness_mm'      => $thickness,
                        'section'           => null,
                        'lines'             => 0,
                        'total_qty'         => 0.0,
                        'uom'               => $uomCode,
                        'total_weight'      => 0.0,
                        'total_length_mm'   => 0.0,
                        'default_item_id'   => null,
                        'default_item_code' => null,
                        'default_item_name' => null,
                    ];
                }

                $raw[$key]['lines']++;
                $raw[$key]['total_qty']    += $qty;
                $raw[$key]['total_weight'] += $totalWeight;

                if ($itemId && ! $raw[$key]['default_item_id']) {
                    $raw[$key]['default_item_id']   = $itemId;
                    $raw[$key]['default_item_code'] = $itemCode;
                    $raw[$key]['default_item_name'] = $itemName;
                }
            }

            if ($category === BomItemMaterialCategory::STEEL_SECTION->value) {
                // Prefer explicit dimension; fall back to Item master name / code
                $section = $dims['section'] ?? ($item->item->name ?? $item->item->code ?? null);
                $section = $section ? trim($section) : null;

                $key = implode('|', [
                    'section',
                    $grade ?? 'unknown',
                    $section ?? 'na',
                ]);

                if (! isset($raw[$key])) {
                    $raw[$key] = [
                        'category'          => 'section',
                        'grade'             => $grade,
                        'thickness_mm'      => null,
                        'section'           => $section,
                        'lines'             => 0,
                        'total_qty'         => 0.0,
                        'uom'               => $uomCode,
                        'total_weight'      => 0.0,
                        'total_length_mm'   => 0.0,
                        'default_item_id'   => null,
                        'default_item_code' => null,
                        'default_item_name' => null,
                    ];
                }

                $raw[$key]['lines']++;
                $raw[$key]['total_qty']    += $qty;
                $raw[$key]['total_weight'] += $totalWeight;

                if (isset($dims['length_mm'])) {
                    $lengthMm = (float) $dims['length_mm'];
                    $raw[$key]['total_length_mm'] += $lengthMm * $qty;
                }

                if ($itemId && ! $raw[$key]['default_item_id']) {
                    $raw[$key]['default_item_id']   = $itemId;
                    $raw[$key]['default_item_code'] = $itemCode;
                    $raw[$key]['default_item_name'] = $itemName;
                }
            }
        }

        $rawRequirements = collect($raw)
            ->sortBy([
                fn ($g) => $g['category'] ?? '',
                fn ($g) => $g['grade'] ?? '',
                fn ($g) => $g['thickness_mm'] ?? 0,
                fn ($g) => $g['section'] ?? '',
            ])
            ->values()
            ->all();

        return $rawRequirements;
    }

    /**
     * Ensure the given BOM belongs to the given project.
     */
    protected function ensureProjectBom(Project $project, Bom $bom): void
    {
        if ($bom->project_id !== $project->id) {
            abort(404);
        }
    }

    public function debugStock(Project $project, Bom $bom, Request $request): View
    {
        $this->ensureProjectBom($project, $bom);

        $category = $request->string('group_category')->toString();
        $grade    = trim((string) $request->input('grade', ''));
        $thk      = $request->input('thickness_mm');
        $section  = trim((string) $request->input('section_profile', ''));

        $rawRequirements = $this->buildRawRequirements($bom);

        // Try to locate the target group like selectStock()
        $targetGroup = null;
        foreach ($rawRequirements as $group) {
            if ($category && ($group['category'] ?? null) !== $category) {
                continue;
            }

            if ($grade !== '') {
                if (trim((string) ($group['grade'] ?? '')) !== $grade) {
                    continue;
                }
            }

            if ($category === 'plate' && $thk !== null) {
                if ((float) ($group['thickness_mm'] ?? 0) !== (float) $thk) {
                    continue;
                }
            }

            if ($category === 'section' && $section !== '') {
                if (trim((string) ($group['section'] ?? '')) !== $section) {
                    continue;
                }
            }

            $targetGroup = $group;
            break;
        }

        // 1) ALL pieces reserved for this BOM (no filters)
        $allReservedForBom = MaterialStockPiece::query()
            ->with(['item', 'originProject', 'originBom'])
            ->where('reserved_for_project_id', $project->id)
            ->where('reserved_for_bom_id', $bom->id)
            ->orderBy('id')
            ->get();

        // 2) Strict query – roughly same logic as selectStock()
        $reservedStrict = MaterialStockPiece::query()
            ->with('item')
            ->where('reserved_for_project_id', $project->id)
            ->where('reserved_for_bom_id', $bom->id);

        $availableStrict = MaterialStockPiece::query()
            ->with('item')
            ->whereNull('reserved_for_project_id')
            ->whereNull('reserved_for_bom_id');

        if ($targetGroup) {
            if ($targetGroup['category'] === 'plate') {
                $reservedStrict
                    ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                    ->where('thickness_mm', (int) $targetGroup['thickness_mm'])
                    ->whereHas('item', function ($q) use ($targetGroup) {
                        if (! empty($targetGroup['grade'])) {
                            $q->where('grade', $targetGroup['grade']);
                        }
                    });

                $availableStrict
                    ->where('material_category', BomItemMaterialCategory::STEEL_PLATE->value)
                    ->where('thickness_mm', (int) $targetGroup['thickness_mm'])
                    ->whereHas('item', function ($q) use ($targetGroup) {
                        if (! empty($targetGroup['grade'])) {
                            $q->where('grade', $targetGroup['grade']);
                        }
                    });
            } elseif ($targetGroup['category'] === 'section') {
                $reservedStrict
                    ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
                    ->where('section_profile', $targetGroup['section'])
                    ->whereHas('item', function ($q) use ($targetGroup) {
                        if (! empty($targetGroup['grade'])) {
                            $q->where('grade', $targetGroup['grade']);
                        }
                    });

                $availableStrict
                    ->where('material_category', BomItemMaterialCategory::STEEL_SECTION->value)
                    ->where('section_profile', $targetGroup['section'])
                    ->whereHas('item', function ($q) use ($targetGroup) {
                        if (! empty($targetGroup['grade'])) {
                            $q->where('grade', $targetGroup['grade']);
                        }
                    });
            }
        }

        $reservedStrict  = $reservedStrict->orderBy('id')->get();
        $availableStrict = $availableStrict->orderBy('id')->limit(200)->get();

        return view('projects.boms.material_planning.debug', [
            'project'            => $project,
            'bom'                => $bom,
            'rawRequirements'    => $rawRequirements,
            'targetGroup'        => $targetGroup,
            'filters'            => [
                'group_category'  => $category,
                'grade'           => $grade,
                'thickness_mm'    => $thk,
                'section_profile' => $section,
            ],
            'allReservedForBom'  => $allReservedForBom,
            'reservedStrict'     => $reservedStrict,
            'availableStrict'    => $availableStrict,
        ]);
    }
}