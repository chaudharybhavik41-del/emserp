<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.lead_stage.update') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('lead_stage')?->id ?? $this->route('crm_lead_stage')?->id ?? null;

        return [
            'code'        => [
                'nullable',
                'string',
                'max:50',
                'unique:crm_lead_stages,code,' . $id,
            ],
            'name'        => ['required', 'string', 'max:200'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_won'      => ['nullable', 'boolean'],
            'is_lost'     => ['nullable', 'boolean'],
            'is_closed'   => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
