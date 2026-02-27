<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'            => 'required|string|max:50|unique:mail_templates,code',
            'name'            => 'required|string|max:150',
            'type'            => 'nullable|string|max:50',
            'mail_profile_id' => 'nullable|integer|exists:mail_profiles,id',
            'subject'         => 'required|string|max:200',
            'body'            => 'required|string',
            'is_active'       => 'nullable|boolean',
        ];
    }
}
