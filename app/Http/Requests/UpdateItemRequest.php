<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.item.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'material_type_id'        => ['required', 'integer', 'exists:material_types,id'],
            'material_category_id'    => ['required', 'integer', 'exists:material_categories,id'],
            'material_subcategory_id' => ['required', 'integer', 'exists:material_subcategories,id'],
            'uom_id'                  => ['required', 'integer', 'exists:uoms,id'],

            'name'                    => ['required', 'string', 'max:150'],
            'short_name'              => ['nullable', 'string', 'max:100'],
            'grade'                   => ['nullable', 'string', 'max:100'],
            'spec'                    => ['nullable', 'string', 'max:100'],
            'thickness'               => ['nullable', 'numeric', 'min:0'],
            'size'                    => ['nullable', 'string', 'max:100'],
            'density'                 => ['nullable', 'numeric', 'min:0'],
            'weight_per_meter'        => ['nullable', 'numeric', 'min:0'],
            'surface_area_per_meter' => ['nullable', 'numeric', 'min:0'],
            'description'             => ['nullable', 'string', 'max:2000'],

            'expense_account_id'      => ['nullable', 'integer', 'exists:accounts,id'],
            'asset_account_id'        => ['nullable', 'integer', 'exists:accounts,id'],
            'inventory_account_id'    => ['nullable', 'integer', 'exists:accounts,id'],

            'accounting_usage_override' => ['nullable', 'string', 'in:tool_stock,fixed_asset,inventory,consumable,service'],

            'hsn_code'                => ['nullable', 'string', 'max:20'],
            'gst_rate_percent'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gst_effective_from'      => ['nullable', 'date'],

            'brands'                  => ['nullable', 'array'],
            'brands.*'                => ['nullable', 'string', 'max:100'],
            // Default reorder levels (Option A: applies to all brands combined)
            'reorder_min_qty'          => ['nullable', 'numeric', 'min:0'],
            'reorder_target_qty'       => ['nullable', 'numeric', 'min:0', 'gte:reorder_min_qty'],


            'is_active'               => ['nullable', 'boolean'],
        ];
    }

    /**
     * Cross-field taxonomy consistency validation:
     * - category must belong to type
     * - subcategory must belong to category
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $typeId = (int) $this->input('material_type_id');
            $catId  = (int) $this->input('material_category_id');
            $subId  = (int) $this->input('material_subcategory_id');

            if ($typeId <= 0 || $catId <= 0 || $subId <= 0) {
                return;
            }

            $category = MaterialCategory::query()
                ->select(['id', 'material_type_id'])
                ->where('id', $catId)
                ->first();

            if (! $category) {
                return;
            }

            if ((int) $category->material_type_id !== $typeId) {
                $v->errors()->add(
                    'material_category_id',
                    'Selected category does not belong to the selected material type.'
                );
                return;
            }

            $subcat = MaterialSubcategory::query()
                ->select(['id', 'material_category_id'])
                ->where('id', $subId)
                ->first();

            if (! $subcat) {
                return;
            }

            if ((int) $subcat->material_category_id !== $catId) {
                $v->errors()->add(
                    'material_subcategory_id',
                    'Selected subcategory does not belong to the selected category.'
                );
            }
        });
    }
}



