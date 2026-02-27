<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware handles permission
    }

    public function rules(): array
    {
        return [
            'code'           => 'required|string|max:20|unique:uoms,code',
            'name'           => 'required|string|max:100',
            'category'       => 'nullable|string|max:50',
            'decimal_places' => 'required|integer|min:0|max:6',
            'is_active'      => 'nullable|boolean',
        ];
    }
}
