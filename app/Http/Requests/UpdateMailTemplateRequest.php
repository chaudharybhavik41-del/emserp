<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('mail_template')->id ?? null;

        return [
            'code'            => [
                'required',
                'string',
                'max:50',
                Rule::unique('mail_templates', 'code')->ignore($templateId),
            ],
            'name'            => 'required|string|max:150',
            'type'            => 'nullable|string|max:50',
            'mail_profile_id' => 'nullable|integer|exists:mail_profiles,id',
            'subject'         => 'required|string|max:200',
            'body'            => 'required|string',
            'is_active'       => 'nullable|boolean',
        ];
    }
}
