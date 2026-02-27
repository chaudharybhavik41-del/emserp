<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'            => 'required|string|max:50|unique:material_types,code',
            'name'            => 'required|string|max:150',
            'description'     => 'nullable|string|max:500',
            'accounting_usage'=> 'required|string|in:inventory,expense,fixed_asset,mixed',
            'sort_order'      => 'nullable|integer|min:0|max:65535',
            'is_active'       => 'nullable|boolean',
        ];
    }
}
