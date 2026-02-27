<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => [
    		'required',
   			 Rule::exists('machines', 'id')->where(function ($q) {
     	   $q->where('is_active', 1)
          ->where('status', 'active')
          ->where('is_issued', 0);
  			  }),
			],
            'assignment_type' => ['required', 'in:contractor,company_worker'],
            'contractor_party_id' => ['required_if:assignment_type,contractor', 'nullable', 'integer', 'exists:parties,id'],
            'contractor_person_name' => ['required_if:assignment_type,contractor', 'nullable', 'string', 'max:200'],
            'worker_user_id' => ['required_if:assignment_type,company_worker', 'nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:assigned_date'],
            'condition_at_issue' => ['required', 'in:excellent,good,fair,requires_attention'],
            'meter_reading_at_issue' => ['nullable', 'numeric', 'min:0'],
            'issue_remarks' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contractor_party_id' => 'contractor',
            'contractor_person_name' => 'person name',
            'worker_user_id' => 'worker',
        ];
    }
}
