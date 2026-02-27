<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMachineCalibrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'calibration_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:calibration_date'],
            'next_due_date' => ['nullable', 'date', 'after:calibration_date'],
            'calibration_agency' => ['nullable', 'string', 'max:200'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'standard_followed' => ['nullable', 'string', 'max:200'],
            'parameters_calibrated' => ['nullable', 'array'],
            'parameters_calibrated.*' => ['string'],
            'result' => ['required', 'in:pass,pass_with_adjustment,fail'],
            'observations' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'report_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'calibration_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:scheduled,completed,overdue,cancelled'],
            'performed_by' => ['nullable', 'integer', 'exists:users,id'],
            'verified_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
