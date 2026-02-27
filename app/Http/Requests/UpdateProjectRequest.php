<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissions are enforced at controller level (middleware)
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'client_party_id'      => ['required', 'exists:parties,id'],
            'contractor_party_id'  => ['nullable', 'exists:parties,id'],
            'lead_id'              => ['nullable', 'exists:crm_leads,id'],
            'quotation_id'         => ['nullable', 'exists:crm_quotations,id'],
            'status'               => ['required', 'string', 'max:50'],
            'start_date'           => ['nullable', 'date'],
            'end_date'             => ['nullable', 'date', 'after_or_equal:start_date'],

            // Site details
            'site_location'              => ['nullable', 'string', 'max:255'],
            'site_location_url'          => ['nullable', 'url', 'max:255'],
            'site_contact_person_name'   => ['nullable', 'string', 'max:255'],
            'site_contact_person_phone'  => ['nullable', 'string', 'max:50'],

            // TPI / Inspection details
            'has_tpi'            => ['nullable', 'boolean'],
            'tpi_company'        => ['nullable', 'string', 'max:255'],
            'tpi_contact_person' => ['nullable', 'string', 'max:255'],
            'tpi_contact_phone'  => ['nullable', 'string', 'max:50'],

            // Commercial terms (new)
            'payment_terms_days'   => ['nullable', 'integer', 'min:0', 'max:3650'],
            'freight_terms'        => ['nullable', 'string', 'max:255'],
            'project_special_notes'=> ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_tpi' => $this->boolean('has_tpi'),
        ]);
    }
}
