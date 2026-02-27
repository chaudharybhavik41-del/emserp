<?php

namespace App\Http\Requests\Tasks;

use App\Models\Tasks\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_list_id' => 'required|exists:task_lists,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:50000',
            'status_id' => 'required|exists:task_statuses,id',
            'priority_id' => 'nullable|exists:task_priorities,id',
            'assignee_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_minutes' => 'nullable|integer|min:0|max:999999',
            'project_id' => 'nullable|exists:projects,id',
            'bom_id' => 'nullable|exists:boms,id',
            'task_type' => ['nullable', Rule::in(array_keys(Task::TASK_TYPES))],
            'is_milestone' => 'boolean',
            'labels' => 'nullable|array',
            'labels.*' => 'exists:task_labels,id',
            'template_id' => 'nullable|exists:task_templates,id',
        ];
    }

    public function messages(): array
    {
        return [
            'task_list_id.required' => 'Please select a task list.',
            'title.required' => 'Task title is required.',
            'title.max' => 'Task title cannot exceed 500 characters.',
            'due_date.after_or_equal' => 'Due date must be after or equal to start date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'company_id' => auth()->user()->company_id ?? 1,
            'is_milestone' => $this->boolean('is_milestone'),
        ];

        if ($this->filled('estimated_hours')) {
            $hours = (float) $this->input('estimated_hours');
            $data['estimated_minutes'] = max(0, (int) round($hours * 60));
        }

        $this->merge($data);
    }
}
