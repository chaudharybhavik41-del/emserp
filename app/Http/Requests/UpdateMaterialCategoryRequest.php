<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('material_category');
        $id = $category?->id;

        return [
            'material_type_id' => ['required', 'integer', 'exists:material_types,id'],
            'code'             => [
                'required',
                'string',
                'max:50',
                Rule::unique('material_categories', 'code')
                    ->where(fn ($query) => $query->where('material_type_id', $this->input('material_type_id')))
                    ->ignore($id),
            ],
            'name'             => ['required', 'string', 'max:150'],
            'description'      => ['nullable', 'string', 'max:500'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'        => ['nullable', 'boolean'],
        ];
    }
}
