<?php

namespace App\Http\Controllers;

use App\Enums\BomItemMaterialCategory;
use App\Enums\MaterialStockPieceStatus;
use App\Models\Item;
use App\Models\MaterialStockPiece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialStockPieceController extends Controller
{
    public function index(Request $request): View
    {
        $query = MaterialStockPiece::query()
            ->with(['item', 'originProject', 'originBom', 'mother'])
            ->orderByDesc('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($materialCategory = $request->string('material_category')->toString()) {
            $query->where('material_category', $materialCategory);
        }

        if ($grade = $request->string('grade')->toString()) {
            $query->whereHas('item', function ($q) use ($grade) {
                $q->where('grade', 'like', '%' . $grade . '%');
            });
        }

        if ($thickness = $request->integer('thickness_mm')) {
            $query->where('thickness_mm', $thickness);
        }

        if ($heat = $request->string('heat_number')->toString()) {
            $query->where('heat_number', 'like', '%' . $heat . '%');
        }

        if ($plate = $request->string('plate_number')->toString()) {
            $query->where('plate_number', 'like', '%' . $plate . '%');
        }

        $pieces = $query->paginate(50)->withQueryString();

        $statusOptions = MaterialStockPieceStatus::options();

        $materialCategories = [
            BomItemMaterialCategory::STEEL_PLATE->value   => 'Steel Plate',
            BomItemMaterialCategory::STEEL_SECTION->value => 'Steel Section',
        ];

        return view('material_stock_pieces.index', compact(
            'pieces',
            'statusOptions',
            'materialCategories'
        ));
    }

    public function create(Request $request): View
    {
        // Limit to RAW material items (plates/sections/etc.)
        $items = Item::query()
            ->with(['type', 'category'])
            ->whereHas('type', function ($q) {
                $q->where('code', 'RAW');
            })
            ->orderBy('code')
            ->get();

        $materialCategories = [
            BomItemMaterialCategory::STEEL_PLATE->value   => 'Steel Plate',
            BomItemMaterialCategory::STEEL_SECTION->value => 'Steel Section',
        ];

        return view('material_stock_pieces.create', [
            'items'             => $items,
            'materialCategories'=> $materialCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedCategories = [
            BomItemMaterialCategory::STEEL_PLATE->value,
            BomItemMaterialCategory::STEEL_SECTION->value,
        ];

        $validated = $request->validate([
            'item_id'          => ['required', 'exists:items,id'],
            'material_category'=> ['required', 'in:' . implode(',', $allowedCategories)],
            'thickness_mm'     => ['nullable', 'integer', 'min:1'],
            'width_mm'         => ['nullable', 'integer', 'min:1'],
            'length_mm'        => ['nullable', 'integer', 'min:1'],
            'section_profile'  => ['nullable', 'string', 'max:100'],
            'weight_kg'        => ['nullable', 'numeric', 'min:0'],
            'plate_number'     => ['nullable', 'string', 'max:50'],
            'heat_number'      => ['nullable', 'string', 'max:100'],
            'mtc_number'       => ['nullable', 'string', 'max:100'],
            'location'         => ['nullable', 'string', 'max:100'],
            'remarks'          => ['nullable', 'string'],
        ]);

        // Auto-calc weight if not provided and enough geometry is available
        if (empty($validated['weight_kg'])) {
            /** @var \App\Models\Item|null $item */
            $item = Item::query()->find($validated['item_id'] ?? null);

            if ($item) {
                $category = $validated['material_category'];

                // Plates: use thickness * width * length * density
                if ($category === BomItemMaterialCategory::STEEL_PLATE->value
                    && ! empty($validated['thickness_mm'])
                    && ! empty($validated['width_mm'])
                    && ! empty($validated['length_mm'])
                ) {
                    $density = $item->density ?: 7850; // default for structural steel

                    $volumeM3 = ($validated['thickness_mm'] / 1000)
                        * ($validated['width_mm'] / 1000)
                        * ($validated['length_mm'] / 1000);

                    $validated['weight_kg'] = round($volumeM3 * $density, 3);
                }

                // Sections: use item's weight_per_meter * length_m
                if ($category === BomItemMaterialCategory::STEEL_SECTION->value
                    && ! empty($validated['length_mm'])
                    && $item->weight_per_meter
                ) {
                    $lengthM = $validated['length_mm'] / 1000;
                    $validated['weight_kg'] = round($item->weight_per_meter * $lengthM, 3);
                }
            }
        }

        $validated['status'] = MaterialStockPieceStatus::AVAILABLE;

        /** @var \App\Models\MaterialStockPiece $piece */
        $piece = MaterialStockPiece::create($validated);

        return redirect()
            ->route('material-stock-pieces.show', $piece)
            ->with('success', 'Material stock piece created successfully.');
    }

    public function show(MaterialStockPiece $materialStockPiece): View
    {
        $materialStockPiece->load([
            'item',
            'originProject',
            'originBom',
            'mother',
            'remnants',
        ]);

        return view('material_stock_pieces.show', [
            'piece' => $materialStockPiece,
        ]);
    }
}
