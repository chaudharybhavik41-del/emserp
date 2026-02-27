@extends('layouts.erp')

@section('title', isset($task->id) ? 'Edit Task' : 'Create Task')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-{{ isset($task->id) ? 'pencil' : 'plus-circle' }} me-2"></i>
                {{ isset($task->id) ? 'Edit Task: ' . $task->task_number : 'Create New Task' }}
            </h1>
        </div>
        <a href="{{ isset($task->id) ? route('tasks.show', $task) : route('tasks.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <form action="{{ isset($task->id) ? route('tasks.update', $task) : route('tasks.store') }}" method="POST">
        @csrf
        @if(isset($task->id))
        @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-body">
                        @if(!isset($task->id) && isset($templates) && $templates->count() > 0)
                        <div class="mb-3">
                            <label class="form-label">Use Template</label>
                            <select name="template_id" class="form-select">
                                <option value="">-- No Template --</option>
                                @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title', $task->title ?? '') }}" required autofocus>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="5">{{ old('description', $task->description ?? '') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Task Type</label>
                            <select name="task_type" class="form-select">
                                @foreach(\App\Models\Tasks\Task::TASK_TYPES as $value => $label)
                                <option value="{{ $value }}" {{ old('task_type', $task->task_type ?? 'general') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Labels</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($labels as $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="labels[]" value="{{ $label->id }}" 
                                           id="label-{{ $label->id }}"
                                           {{ in_array($label->id, old('labels', isset($task) ? $task->labels->pluck('id')->toArray() : [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="label-{{ $label->id }}">
                                        <span class="badge" style="background-color: {{ $label->color }}">{{ $label->name }}</span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Time & Progress</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Estimated Time (hours)</label>
                                <input type="number" name="estimated_hours" class="form-control" 
                                       value="{{ old('estimated_hours', isset($task->estimated_minutes) ? round($task->estimated_minutes / 60, 1) : '') }}"
                                       step="0.5" min="0">
                            </div>
                            @if(isset($task->id))
                            <div class="col-md-4">
                                <label class="form-label">Progress (%)</label>
                                <input type="number" name="progress_percent" class="form-control" 
                                       value="{{ old('progress_percent', $task->progress_percent ?? 0) }}" min="0" max="100">
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>Task Settings</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Task List <span class="text-danger">*</span></label>
                            <select name="task_list_id" class="form-select @error('task_list_id') is-invalid @enderror" required>
                                <option value="">Select a list...</option>
                                @foreach($taskLists as $list)
                                <option value="{{ $list->id }}" {{ old('task_list_id', $task->task_list_id ?? '') == $list->id ? 'selected' : '' }}>
                                    {{ $list->name }} @if($list->project)({{ $list->project->code }})@endif
                                </option>
                                @endforeach
                            </select>
                            @error('task_list_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        @if(isset($task->parent_id))
                        <input type="hidden" name="parent_id" value="{{ $task->parent_id }}">
                        @endif

                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_id" class="form-select @error('status_id') is-invalid @enderror" required>
                                @foreach($statuses as $status)
                                <option value="{{ $status->id }}" {{ old('status_id', $task->status_id ?? '') == $status->id ? 'selected' : '' }}>
                                    {{ $status->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority_id" class="form-select">
                                <option value="">No Priority</option>
                                @foreach($priorities as $priority)
                                <option value="{{ $priority->id }}" {{ old('priority_id', $task->priority_id ?? '') == $priority->id ? 'selected' : '' }}>
                                    {{ $priority->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assignee</label>
                            <select name="assignee_id" class="form-select">
                                <option value="">Unassigned</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('assignee_id', $task->assignee_id ?? '') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Dates</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="{{ old('start_date', isset($task->start_date) ? $task->start_date->format('Y-m-d') : '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" 
                                   value="{{ old('due_date', isset($task->due_date) ? $task->due_date->format('Y-m-d') : '') }}">
                            @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Link to Project/BOM</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Project</label>
                            <select name="project_id" class="form-select" id="projectSelect">
                                <option value="">No Project</option>
                                @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ old('project_id', $task->project_id ?? '') == $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">BOM</label>
                            <select name="bom_id" class="form-select" {{ ($boms ?? collect())->isEmpty() ? 'disabled' : '' }}>
                                <option value="">{{ ($boms ?? collect())->isEmpty() ? 'Select a project to load BOMs' : 'No BOM' }}</option>
                                @foreach(($boms ?? collect()) as $bom)
                                <option value="{{ $bom->id }}" {{ old('bom_id', $task->bom_id ?? '') == $bom->id ? 'selected' : '' }}>
                                    {{ $bom->bom_number }}
                                </option>
                                @endforeach
                            </select>
                            @if(($boms ?? collect())->isEmpty())
                                <small class="text-muted">BOM options are loaded from the selected project.</small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Options</strong></div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_milestone" value="1" 
                                   id="isMilestone" {{ old('is_milestone', $task->is_milestone ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isMilestone">
                                <i class="bi bi-flag me-1"></i> Mark as Milestone
                            </label>
                        </div>
                        @if(isset($task->id))
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_blocked" value="1" 
                                   id="isBlocked" {{ old('is_blocked', $task->is_blocked ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isBlocked">
                                <i class="bi bi-exclamation-octagon me-1"></i> Mark as Blocked
                            </label>
                        </div>
                        <div class="mt-2" id="blockedReasonDiv" style="{{ old('is_blocked', $task->is_blocked ?? false) ? '' : 'display:none' }}">
                            <textarea name="blocked_reason" class="form-control form-control-sm" rows="2" 
                                      placeholder="Reason for being blocked...">{{ old('blocked_reason', $task->blocked_reason ?? '') }}</textarea>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> {{ isset($task->id) ? 'Update Task' : 'Create Task' }}
                    </button>
                    @if(!isset($task->id))
                    <button type="submit" name="create_another" value="1" class="btn btn-outline-primary">
                        <i class="bi bi-plus me-1"></i> Create & Add Another
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const blockedCheckbox = document.getElementById('isBlocked');
    const blockedReasonDiv = document.getElementById('blockedReasonDiv');
    
    if (blockedCheckbox && blockedReasonDiv) {
        blockedCheckbox.addEventListener('change', function() {
            blockedReasonDiv.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
@endpush
@endsection
