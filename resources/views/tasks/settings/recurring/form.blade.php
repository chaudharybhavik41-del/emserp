@extends('layouts.erp')

@section('title', isset($schedule->id) ? 'Edit Recurring Schedule' : 'Create Recurring Schedule')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ isset($schedule->id) ? 'Edit' : 'Create' }} Recurring Schedule</h1>
        <a href="{{ route('task-settings.recurring.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form action="{{ isset($schedule->id) ? route('task-settings.recurring.update', $schedule) : route('task-settings.recurring.store') }}" method="POST">
        @csrf
        @if(isset($schedule->id))
        @method('PUT')
        @endif
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $schedule->name) }}" required></div>
                    <div class="mb-3"><label class="form-label">Task List</label><select name="task_list_id" class="form-select">@foreach($taskLists as $taskList)<option value="{{ $taskList->id }}" {{ old('task_list_id', $schedule->task_list_id) == $taskList->id ? 'selected' : '' }}>{{ $taskList->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">Template</label><select name="template_id" class="form-select"><option value="">No template</option>@foreach($templates as $template)<option value="{{ $template->id }}" {{ old('template_id', $schedule->template_id) == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">Title Template</label><input type="text" name="title_template" class="form-control" value="{{ old('title_template', $schedule->title_template) }}" required></div>
                    <div class="mb-3"><label class="form-label">Description Template</label><textarea name="description_template" class="form-control" rows="4">{{ old('description_template', $schedule->description_template) }}</textarea></div>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Interval Unit</label><select name="interval_unit" class="form-select">@foreach(['daily', 'weekly', 'monthly'] as $unit)<option value="{{ $unit }}" {{ old('interval_unit', $schedule->interval_unit ?: 'weekly') === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Every</label><input type="number" name="interval_value" class="form-control" min="1" max="30" value="{{ old('interval_value', $schedule->interval_value ?: 1) }}"></div>
                        <div class="col-md-6"><label class="form-label">Next Run</label><input type="datetime-local" name="next_run_at" class="form-control" value="{{ old('next_run_at', $schedule->next_run_at?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}"></div>
                        <div class="col-md-6"><label class="form-label">Assignee</label><select name="assignee_id" class="form-select"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}" {{ old('assignee_id', $schedule->assignee_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select name="status_id" class="form-select"><option value="">Default list status</option>@foreach($statuses as $status)<option value="{{ $status->id }}" {{ old('status_id', $schedule->status_id) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Priority</label><select name="priority_id" class="form-select"><option value="">Default list priority</option>@foreach($priorities as $priority)<option value="{{ $priority->id }}" {{ old('priority_id', $schedule->priority_id) == $priority->id ? 'selected' : '' }}>{{ $priority->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Task Type</label><select name="task_type" class="form-select">@foreach(\App\Models\Tasks\Task::TASK_TYPES as $value => $label)<option value="{{ $value }}" {{ old('task_type', $schedule->task_type ?: 'general') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Estimated Minutes</label><input type="number" name="estimated_minutes" class="form-control" min="0" value="{{ old('estimated_minutes', $schedule->estimated_minutes) }}"></div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="scheduleActive" {{ old('is_active', $schedule->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="scheduleActive">Active</label>
                    </div>
                </div></div>
            </div>
        </div>
        <div class="mt-3"><button type="submit" class="btn btn-primary">{{ isset($schedule->id) ? 'Update' : 'Create' }} Schedule</button></div>
    </form>
</div>
@endsection
