<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmLeadSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.lead_source.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('lead_source')?->id ?? $this->route('crm_lead_source')?->id ?? null;

        return [
            'code' => [
                'nullable',
                'string',
                'max:50',
                'unique:crm_lead_sources,code,' . $id,
            ],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
