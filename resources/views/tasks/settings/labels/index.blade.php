@extends('layouts.erp')

@section('title', 'Task Labels')

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
            <a class="nav-link active" href="{{ route('task-settings.labels.index') }}">
                <i class="bi bi-tag me-1"></i> Labels
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.templates.index') }}">
                <i class="bi bi-file-earmark-text me-1"></i> Templates
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Task Labels</span>
            @can('tasks.settings.manage')
            <a href="{{ route('task-settings.labels.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus me-1"></i> Add Label
            </a>
            @endcan
        </div>

        <div class="px-3 pt-3 pb-2 border-bottom">
            <form method="GET" action="{{ route('task-settings.labels.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label mb-1">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="Name / Description">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Sort</label>
                    <div class="input-group input-group-sm">
                        <select name="sort" class="form-select form-select-sm">
                            <option value="">Name</option>
                            <option value="tasks_count" {{ request('sort') === 'tasks_count' ? 'selected' : '' }}>Tasks</option>
                            <option value="is_active" {{ request('sort') === 'is_active' ? 'selected' : '' }}>Status</option>
                        </select>
                        <select name="dir" class="form-select form-select-sm" style="max-width: 110px;">
                            <option value="asc" {{ request('dir', 'asc') === 'asc' ? 'selected' : '' }}>Asc</option>
                            <option value="desc" {{ request('dir') === 'desc' ? 'selected' : '' }}>Desc</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                    <a href="{{ route('task-settings.labels.index') }}" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Label</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th class="text-center">Tasks</th>
                        <th style="width: 120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($labels as $label)
                    <tr>
                        <td>
                            <span class="badge" style="background-color: {{ $label->color }}; color: {{ $label->text_color }};">
                                {{ $label->name }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $label->description ?? '-' }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 20px; height: 20px; background: {{ $label->color }}; border-radius: 4px;"></div>
                                <code class="small">{{ $label->color }}</code>
                            </div>
                        </td>
                        <td class="text-center">{{ $label->tasks_count }}</td>
                        <td>
                            @can('tasks.settings.manage')
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('task-settings.labels.edit', $label) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="if(confirm('Delete this label?')) document.getElementById('delete-{{ $label->id }}').submit();">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="delete-{{ $label->id }}" action="{{ route('task-settings.labels.destroy', $label) }}" method="POST" class="d-none">
                                    @csrf @method('DELETE')
                                </form>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No labels defined yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


