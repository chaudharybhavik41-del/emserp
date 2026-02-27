@extends('layouts.erp')

@section('title', isset($taskList->id) ? 'Edit Task List' : 'Create Task List')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-{{ isset($taskList->id) ? 'pencil' : 'folder-plus' }} me-2"></i>
                {{ isset($taskList->id) ? 'Edit Task List: ' . $taskList->name : 'Create New Task List' }}
            </h1>
        </div>
        <a href="{{ route('task-lists.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Lists
        </a>
    </div>

    <form action="{{ isset($taskList->id) ? route('task-lists.update', $taskList) : route('task-lists.store') }}" method="POST">
        @csrf
        @if(isset($taskList->id))
        @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><strong>List Details</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">List Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $taskList->name ?? '') }}" required autofocus
                                   placeholder="e.g., Project Alpha Tasks, Q1 Sprint">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="3" placeholder="Optional description for this task list...">{{ old('description', $taskList->description ?? '') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Color</label>
                                    <div class="input-group">
                                        <input type="color" name="color" class="form-control form-control-color" 
                                               value="{{ old('color', $taskList->color ?? '#0d6efd') }}" id="colorPicker">
                                        <input type="text" class="form-control" id="colorText" 
                                               value="{{ old('color', $taskList->color ?? '#0d6efd') }}" readonly style="max-width: 100px;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Icon (Bootstrap Icons)</label>
                                    <input type="text" name="icon" class="form-control" 
                                           value="{{ old('icon', $taskList->icon ?? '') }}" 
                                           placeholder="e.g., bi-folder-fill">
                                    <small class="text-muted">See <a href="https://icons.getbootstrap.com/" target="_blank">icons.getbootstrap.com</a></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Default Settings for New Tasks</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Default Status</label>
                                    <select name="default_status_id" class="form-select">
                                        <option value="">Use System Default</option>
                                        @foreach($statuses as $status)
                                        <option value="{{ $status->id }}" 
                                                {{ old('default_status_id', $taskList->default_status_id ?? '') == $status->id ? 'selected' : '' }}>
                                            {{ $status->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Default Priority</label>
                                    <select name="default_priority_id" class="form-select">
                                        <option value="">Use System Default</option>
                                        @foreach($priorities as $priority)
                                        <option value="{{ $priority->id }}" 
                                                {{ old('default_priority_id', $taskList->default_priority_id ?? '') == $priority->id ? 'selected' : '' }}>
                                            {{ $priority->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Default Assignee</label>
                                    <select name="default_assignee_id" class="form-select">
                                        <option value="">Unassigned</option>
                                        @foreach($users as $user)
                                        <option value="{{ $user->id }}" 
                                                {{ old('default_assignee_id', $taskList->default_assignee_id ?? '') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>Organization</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Link to Project</label>
                            <select name="project_id" class="form-select">
                                <option value="">No Project</option>
                                @foreach($projects as $project)
                                <option value="{{ $project->id }}" 
                                        {{ old('project_id', $taskList->project_id ?? request('project')) == $project->id ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Link this list to a project</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parent List</label>
                            <select name="parent_id" class="form-select">
                                <option value="">None (Root Level)</option>
                                @foreach($parentLists as $parent)
                                @if(!isset($taskList->id) || $parent->id != $taskList->id)
                                <option value="{{ $parent->id }}" 
                                        {{ old('parent_id', $taskList->parent_id ?? '') == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }}
                                </option>
                                @endif
                                @endforeach
                            </select>
                            <small class="text-muted">Create nested folder structure</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Visibility & Access</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Visibility</label>
                            <select name="visibility" class="form-select">
                                <option value="team" {{ old('visibility', $taskList->visibility ?? 'team') === 'team' ? 'selected' : '' }}>
                                    Team - Visible to all team members
                                </option>
                                <option value="private" {{ old('visibility', $taskList->visibility ?? '') === 'private' ? 'selected' : '' }}>
                                    Private - Only visible to me and added members
                                </option>
                                <option value="public" {{ old('visibility', $taskList->visibility ?? '') === 'public' ? 'selected' : '' }}>
                                    Public - Visible to everyone
                                </option>
                            </select>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" 
                                   id="isActive" {{ old('is_active', $taskList->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> {{ isset($taskList->id) ? 'Update List' : 'Create List' }}
                    </button>
                    <a href="{{ route('task-lists.index') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('colorPicker').addEventListener('input', function() {
    document.getElementById('colorText').value = this.value;
});
</script>
@endpush
@endsection
