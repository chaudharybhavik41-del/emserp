@extends('layouts.erp')

@section('title', isset($rule->id) ? 'Edit Task Automation' : 'Create Task Automation')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ isset($rule->id) ? 'Edit' : 'Create' }} Task Automation</h1>
        <a href="{{ route('task-settings.automations.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form action="{{ isset($rule->id) ? route('task-settings.automations.update', $rule) : route('task-settings.automations.store') }}" method="POST">
        @csrf
        @if(isset($rule->id))
        @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Rule</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $rule->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Trigger Event</label>
                            <select name="trigger_event" class="form-select">
                                @foreach(['created', 'updated', 'status_changed', 'comment_added', 'overdue'] as $trigger)
                                <option value="{{ $trigger }}" {{ old('trigger_event', $rule->trigger_event ?: 'updated') === $trigger ? 'selected' : '' }}>
                                    {{ str_replace('_', ' ', ucfirst($trigger)) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Task List Scope</label>
                            <select name="task_list_id" class="form-select">
                                <option value="">All Task Lists</option>
                                @foreach($taskLists as $taskList)
                                <option value="{{ $taskList->id }}" {{ old('task_list_id', $rule->task_list_id) == $taskList->id ? 'selected' : '' }}>{{ $taskList->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', $rule->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><strong>Conditions</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="condition_status_id" class="form-select">
                                <option value="">Any status</option>
                                @foreach($statuses as $status)
                                <option value="{{ $status->id }}" {{ old('condition_status_id', data_get($rule->conditions, 'status_id')) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="condition_priority_id" class="form-select">
                                <option value="">Any priority</option>
                                @foreach($priorities as $priority)
                                <option value="{{ $priority->id }}" {{ old('condition_priority_id', data_get($rule->conditions, 'priority_id')) == $priority->id ? 'selected' : '' }}>{{ $priority->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Task Type</label>
                            <select name="condition_task_type" class="form-select">
                                <option value="">Any type</option>
                                @foreach(\App\Models\Tasks\Task::TASK_TYPES as $value => $label)
                                <option value="{{ $value }}" {{ old('condition_task_type', data_get($rule->conditions, 'task_type')) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title Contains</label>
                            <input type="text" name="condition_title_contains" class="form-control" value="{{ old('condition_title_contains', data_get($rule->conditions, 'title_contains')) }}">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Actions</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Set Status</label>
                            <select name="action_set_status_id" class="form-select">
                                <option value="">No change</option>
                                @foreach($statuses as $status)
                                <option value="{{ $status->id }}" {{ old('action_set_status_id', data_get($rule->actions, 'set_status_id')) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Set Priority</label>
                            <select name="action_set_priority_id" class="form-select">
                                <option value="">No change</option>
                                @foreach($priorities as $priority)
                                <option value="{{ $priority->id }}" {{ old('action_set_priority_id', data_get($rule->actions, 'set_priority_id')) == $priority->id ? 'selected' : '' }}>{{ $priority->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign User</label>
                            <select name="action_assign_user_id" class="form-select">
                                <option value="">No auto-assignment</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('action_assign_user_id', data_get($rule->actions, 'assign_user_id')) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notify Users</label>
                            <select name="action_notify_user_ids[]" class="form-select" multiple size="5">
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ in_array($user->id, old('action_notify_user_ids', data_get($rule->actions, 'notify_user_ids', []))) ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Add Watchers</label>
                            <select name="action_add_watcher_ids[]" class="form-select" multiple size="5">
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ in_array($user->id, old('action_add_watcher_ids', data_get($rule->actions, 'add_watcher_ids', []))) ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">{{ isset($rule->id) ? 'Update' : 'Create' }} Rule</button>
        </div>
    </form>
</div>
@endsection
