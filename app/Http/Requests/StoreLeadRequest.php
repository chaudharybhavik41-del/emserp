<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.lead.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', 'unique:crm_leads,code'],

            'title'   => ['required', 'string', 'max:255'],
            'party_id' => ['nullable', 'integer', 'exists:parties,id'],

            'contact_name'  => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],

            'lead_source_id' => ['nullable', 'integer', 'exists:crm_lead_sources,id'],
            'lead_stage_id'  => ['nullable', 'integer', 'exists:crm_lead_stages,id'],

            'expected_value'      => ['nullable', 'numeric'],
            'probability'         => ['nullable', 'integer', 'between:0,100'],
            'lead_date'           => ['nullable', 'date'],
            'expected_close_date' => ['nullable', 'date'],

            'owner_id'      => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],

            'notes' => ['nullable', 'string'],
        ];
    }
}
