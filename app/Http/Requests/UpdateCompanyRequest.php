<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id ?? null;

        return [
            'code'          => [
                'required',
                'string',
                'max:20',
                Rule::unique('companies', 'code')->ignore($companyId),
            ],
            'name'          => 'required|string|max:150',
            'legal_name'    => 'nullable|string|max:200',
            'gst_number'    => 'nullable|string|max:20',
            'pan'           => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:150',
            'phone'         => 'nullable|string|max:50',
            'website'       => 'nullable|string|max:150',
            'address_line1' => 'nullable|string|max:200',
            'address_line2' => 'nullable|string|max:200',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'pincode'       => 'nullable|string|max:20',
            'country'       => 'nullable|string|max:100',
            'is_default'    => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ];
    }
}
