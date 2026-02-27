@extends('layouts.erp')

@section('title', 'Task Templates')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-gear me-2"></i>Task Settings
            </h1>
        </div>
    </div>

    {{-- Settings Tabs --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.statuses.index') }}">
                <i class="bi bi-circle me-1"></i> Statuses
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.priorities.index') }}">
                <i class="bi bi-flag me-1"></i> Priorities
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.labels.index') }}">
                <i class="bi bi-tag me-1"></i> Labels
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('task-settings.templates.index') }}">
                <i class="bi bi-file-earmark-text me-1"></i> Templates
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Task Templates</span>
            @can('tasks.settings.manage')
            <a href="{{ route('task-settings.templates.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus me-1"></i> Add Template
            </a>
            @endcan
        </div>

        <div class="px-3 pt-3 pb-2 border-bottom">
            <form method="GET" action="{{ route('task-settings.templates.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label mb-1">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="Name / Description / Title template">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Task Type</label>
                    <select name="task_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($taskTypes as $k => $label)
                            <option value="{{ $k }}" {{ (string) request('task_type') === (string) $k ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Sort</label>
                    <div class="input-group input-group-sm">
                        <select name="sort" class="form-select form-select-sm">
                            <option value="">Name</option>
                            <option value="task_type" {{ request('sort') === 'task_type' ? 'selected' : '' }}>Task type</option>
                            <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>Created</option>
                        </select>
                        <select name="dir" class="form-select form-select-sm" style="max-width: 110px;">
                            <option value="asc" {{ request('dir', 'asc') === 'asc' ? 'selected' : '' }}>Asc</option>
                            <option value="desc" {{ request('dir') === 'desc' ? 'selected' : '' }}>Desc</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                    <a href="{{ route('task-settings.templates.index') }}" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Default Status</th>
                        <th>Est. Time</th>
                        <th>Checklists</th>
                        <th class="text-center">Active</th>
                        <th style="width: 120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                    <tr>
                        <td>
                            <strong>{{ $template->name }}</strong>
                            @if($template->description)
                            <br><small class="text-muted">{{ Str::limit($template->description, 50) }}</small>
                            @endif
                        </td>
                        <td>
                            @if($template->task_type)
                            <span class="badge bg-secondary">{{ \App\Models\Tasks\Task::TASK_TYPES[$template->task_type] ?? $template->task_type }}</span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($template->defaultStatus)
                            <span class="badge" style="background-color: {{ $template->defaultStatus->color }};">
                                {{ $template->defaultStatus->name }}
                            </span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($template->estimated_minutes)
                            {{ floor($template->estimated_minutes / 60) }}h {{ $template->estimated_minutes % 60 }}m
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($template->default_checklist && count($template->default_checklist) > 0)
                            {{ count($template->default_checklist) }} checklist(s)
                            @else
                            <span class="text-muted">None</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($template->is_active)
                            <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                            <i class="bi bi-x-circle text-muted"></i>
                            @endif
                        </td>
                        <td>
                            @can('tasks.settings.manage')
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('task-settings.templates.edit', $template) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="if(confirm('Delete this template?')) document.getElementById('delete-{{ $template->id }}').submit();">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="delete-{{ $template->id }}" action="{{ route('task-settings.templates.destroy', $template) }}" method="POST" class="d-none">
                                    @csrf @method('DELETE')
                                </form>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No templates defined yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Templates allow you to quickly create tasks with predefined settings, checklists, and estimated times.
        </small>
    </div>
</div>
@endsection


