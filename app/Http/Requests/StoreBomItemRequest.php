<?php

namespace App\Http\Requests;

use App\Enums\BomItemMaterialCategory;
use App\Enums\BomItemMaterialSource;
use App\Enums\BomItemProcurementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('project.bom.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'parent_item_id' => ['nullable', 'exists:bom_items,id'],
            'item_code' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'assembly_type' => ['nullable', 'string', 'max:50'],
            'sequence_no' => ['nullable', 'integer', 'min:0'],
            'drawing_number' => ['nullable', 'string', 'max:255'],
            'drawing_revision' => ['nullable', 'string', 'max:255'],

            'material_category' => ['required', new Enum(BomItemMaterialCategory::class)],
            'item_id' => ['nullable', 'exists:items,id'],
            'uom_id' => ['nullable', 'exists:uoms,id'],

            'dimensions' => ['nullable', 'array'],

            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_weight' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'procurement_type' => ['nullable', new Enum(BomItemProcurementType::class)],
            'material_source' => ['nullable', new Enum(BomItemMaterialSource::class)],

            'remarks' => ['nullable', 'string'],
        ];
    }
}
