<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'description'          => ['nullable', 'string'],
            'start_date'           => ['nullable', 'date'],
            'end_date'             => ['nullable', 'date', 'after_or_equal:start_date'],
            'po_number'            => ['nullable', 'string', 'max:100'],
            'po_date'              => ['nullable', 'date'],

            // Site details
            'site_location'        => ['nullable', 'string', 'max:255'],
            'site_location_url'    => ['nullable', 'url', 'max:255'],
            'site_contact_name'    => ['nullable', 'string', 'max:255'],
            'site_contact_phone'   => ['nullable', 'string', 'max:50'],
            'site_contact_email'   => ['nullable', 'email', 'max:255'],

            // TPI / Inspection details
            'has_tpi'            => ['nullable', 'boolean'],
            'tpi_party_id'       => ['nullable', 'exists:parties,id'],
            'tpi_contact_name'   => ['nullable', 'string', 'max:255'],
            'tpi_contact_phone'  => ['nullable', 'string', 'max:50'],
            'tpi_contact_email'  => ['nullable', 'email', 'max:255'],
            'tpi_notes'          => ['nullable', 'string'],

            // Commercial terms (new)
            'payment_terms_days'   => ['nullable', 'integer', 'min:0', 'max:3650'],
            'freight_terms'        => ['nullable', 'string', 'max:255'],
            'project_special_notes' => ['nullable', 'string'],
            'client_billing_mode' => ['nullable', 'string', 'max:40'],
            'client_billing_default_bill_kind' => ['nullable', 'string', 'max:40'],
            'client_billing_source_basis' => ['nullable', 'string', 'max:40'],
            'client_billing_material_scope' => ['nullable', 'string', 'max:30'],
            'client_billing_separate_material_service' => ['nullable', 'boolean'],
            'client_billing_tds_section' => ['nullable', 'string', 'max:20'],
            'client_billing_tds_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'client_billing_notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $siteContactName = $this->input('site_contact_name', $this->input('site_contact_person_name'));
        $siteContactPhone = $this->input('site_contact_phone', $this->input('site_contact_person_phone'));
        $tpiContactName = $this->input('tpi_contact_name', $this->input('tpi_contact_person'));

        $this->merge([
            'has_tpi' => $this->boolean('has_tpi'),
            'client_billing_separate_material_service' => $this->boolean('client_billing_separate_material_service'),
            'site_contact_name' => $siteContactName,
            'site_contact_phone' => $siteContactPhone,
            'tpi_contact_name' => $tpiContactName,
        ]);
    }
}
