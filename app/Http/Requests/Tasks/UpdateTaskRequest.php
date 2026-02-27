<?php

namespace App\Http\Requests\Tasks;

use App\Models\Tasks\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_list_id' => 'sometimes|required|exists:task_lists,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'title' => 'sometimes|required|string|max:500',
            'description' => 'nullable|string|max:50000',
            'status_id' => 'sometimes|required|exists:task_statuses,id',
            'priority_id' => 'nullable|exists:task_priorities,id',
            'assignee_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_minutes' => 'nullable|integer|min:0|max:999999',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'project_id' => 'nullable|exists:projects,id',
            'bom_id' => 'nullable|exists:boms,id',
            'task_type' => ['nullable', Rule::in(array_keys(Task::TASK_TYPES))],
            'is_milestone' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_reason' => 'nullable|string|max:1000',
            'labels' => 'nullable|array',
            'labels.*' => 'exists:task_labels,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('estimated_hours')) {
            $this->merge([
                'estimated_minutes' => max(0, (int) round(((float) $this->input('estimated_hours')) * 60)),
            ]);
        }

        if ($this->has('is_milestone')) {
            $this->merge(['is_milestone' => $this->boolean('is_milestone')]);
        }
        if ($this->has('is_blocked')) {
            $this->merge(['is_blocked' => $this->boolean('is_blocked')]);
        }
    }
}
