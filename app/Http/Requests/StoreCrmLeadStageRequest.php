<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrmLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.lead_stage.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code'        => ['nullable', 'string', 'max:50', 'unique:crm_lead_stages,code'],
            'name'        => ['required', 'string', 'max:200'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_won'      => ['nullable', 'boolean'],
            'is_lost'     => ['nullable', 'boolean'],
            'is_closed'   => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
