<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendMachineAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_expected_return_date' => ['required', 'date', 'after:today'],
            'extension_reason' => ['nullable', 'string'],
        ];
    }
}
