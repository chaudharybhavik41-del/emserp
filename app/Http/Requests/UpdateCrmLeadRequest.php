<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permissions are handled via middleware, so allow here.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // We don't change code in the controller, so it’s optional
            'code'                => ['sometimes', 'string', 'max:50'],
            'title'               => ['required', 'string', 'max:255'],
            'party_id'            => ['nullable', 'integer', 'exists:parties,id'],
            'contact_name'        => ['nullable', 'string', 'max:255'],
            'contact_email'       => ['nullable', 'email', 'max:255'],
            'contact_phone'       => ['nullable', 'string', 'max:50'],
            'lead_source_id'      => ['nullable', 'integer', 'exists:crm_lead_sources,id'],
            'lead_stage_id'       => ['nullable', 'integer', 'exists:crm_lead_stages,id'],
            'expected_value'      => ['nullable', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'lead_date'           => ['nullable', 'date'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id'            => ['nullable', 'integer', 'exists:users,id'],
            'department_id'       => ['nullable', 'integer', 'exists:departments,id'],
            'notes'               => ['nullable', 'string'],
        ];
    }
}
