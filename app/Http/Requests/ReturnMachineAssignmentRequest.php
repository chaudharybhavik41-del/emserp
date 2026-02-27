<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnMachineAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_return_date'      => ['required', 'date'],

            // New Phase-C fields
            'return_disposition'      => ['required', 'in:returned,scrapped'],
            'damage_borne_by'         => ['nullable', 'in:company,contractor,shared', 'required_if:return_disposition,scrapped'],
            'damage_recovery_amount'  => ['nullable', 'numeric', 'min:0', 'required_if:damage_borne_by,contractor', 'required_if:damage_borne_by,shared'],

            // Existing fields
            'condition_at_return'     => ['required', 'in:good,minor_wear,damaged,not_returned'],
            'meter_reading_at_return' => ['nullable', 'numeric', 'min:0'],
            'return_remarks'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
