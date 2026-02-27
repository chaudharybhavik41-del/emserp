<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.material_subcategory.update') ?? false;
    }

    public function rules(): array
    {
        $subcategory = $this->route('material_subcategory');
        $categoryId  = $this->input('material_category_id', $subcategory?->material_category_id);

        return [
            'material_category_id' => ['required', 'integer', 'exists:material_categories,id'],

            // Code is frozen – we still validate uniqueness but user cannot change it in UI
            'code'                 => [
                'required',
                'string',
                'max:50',
                Rule::unique('material_subcategories', 'code')
                    ->where(fn ($q) => $q->where('material_category_id', $categoryId))
                    ->ignore($subcategory?->id),
            ],

            // NEW – editable prefix for item codes
            'item_code_prefix'     => ['nullable', 'string', 'min:1', 'max:10'],

            'name'                 => ['required', 'string', 'max:150'],
            'description'          => ['nullable', 'string', 'max:500'],
            'expense_account_id'   => ['nullable', 'integer', 'exists:accounts,id'],
            'asset_account_id'     => ['nullable', 'integer', 'exists:accounts,id'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sort_order'           => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'            => ['sometimes', 'boolean'],
        ];
    }
}
