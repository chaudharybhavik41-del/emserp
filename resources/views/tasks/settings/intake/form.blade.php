@extends('layouts.erp')

@section('title', isset($form->id) ? 'Edit Intake Form' : 'Create Intake Form')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ isset($form->id) ? 'Edit' : 'Create' }} Intake Form</h1>
        <a href="{{ route('task-settings.intake-forms.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form action="{{ isset($form->id) ? route('task-settings.intake-forms.update', $form) : route('task-settings.intake-forms.store') }}" method="POST">
        @csrf
        @if(isset($form->id))
        @method('PUT')
        @endif
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $form->name) }}" required></div>
                    <div class="mb-3"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="{{ old('slug', $form->slug) }}"></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4">{{ old('description', $form->description) }}</textarea></div>
                    <div class="mb-3"><label class="form-label">Task List</label><select name="task_list_id" class="form-select">@foreach($taskLists as $taskList)<option value="{{ $taskList->id }}" {{ old('task_list_id', $form->task_list_id) == $taskList->id ? 'selected' : '' }}>{{ $taskList->name }}</option>@endforeach</select></div>
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Default Status</label><select name="default_status_id" class="form-select"><option value="">List default</option>@foreach($statuses as $status)<option value="{{ $status->id }}" {{ old('default_status_id', $form->default_status_id) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Default Priority</label><select name="default_priority_id" class="form-select"><option value="">List default</option>@foreach($priorities as $priority)<option value="{{ $priority->id }}" {{ old('default_priority_id', $form->default_priority_id) == $priority->id ? 'selected' : '' }}>{{ $priority->name }}</option>@endforeach</select></div>
                        <div class="col-md-12"><label class="form-label">Default Assignee</label><select name="default_assignee_id" class="form-select"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}" {{ old('default_assignee_id', $form->default_assignee_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label">Success Message</label><textarea name="success_message" class="form-control" rows="2">{{ old('success_message', $form->success_message) }}</textarea></div>
                    </div>
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="requires_auth" value="1" id="requiresAuth" {{ old('requires_auth', $form->requires_auth ?? true) ? 'checked' : '' }}><label class="form-check-label" for="requiresAuth">Requires auth</label></div>
                    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="formActive" {{ old('is_active', $form->is_active ?? true) ? 'checked' : '' }}><label class="form-check-label" for="formActive">Active</label></div>
                </div></div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><strong>Fields</strong></div>
            <div class="card-body">
                @php $existingFields = old('field_labels') ? collect(old('field_labels'))->map(fn ($label, $idx) => ['label' => $label, 'key' => old('field_keys')[$idx] ?? '', 'required' => in_array((string) $idx, array_map('strval', old('field_required', [])), true)]) : collect($form->fields ?? [['label' => 'Title', 'key' => 'title', 'required' => true], ['label' => 'Details', 'key' => 'details', 'required' => false]]); @endphp
                @foreach($existingFields as $index => $field)
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-md-5"><input type="text" name="field_labels[]" class="form-control" value="{{ $field['label'] }}" placeholder="Field label"></div>
                    <div class="col-md-5"><input type="text" name="field_keys[]" class="form-control" value="{{ $field['key'] }}" placeholder="field_key"></div>
                    <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="field_required[]" value="{{ $index }}" {{ $field['required'] ? 'checked' : '' }}><label class="form-check-label">Required</label></div></div>
                </div>
                @endforeach
                <small class="text-muted">This version supports a fixed field list. Add more rows here as needed.</small>
            </div>
        </div>

        <div class="mt-3"><button type="submit" class="btn btn-primary">{{ isset($form->id) ? 'Update' : 'Create' }} Form</button></div>
    </form>
</div>
@endsection
