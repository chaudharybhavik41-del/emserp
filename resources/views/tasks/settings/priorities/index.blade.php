@extends('layouts.erp')

@section('title', 'Task Priorities')

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
            <a class="nav-link active" href="{{ route('task-settings.priorities.index') }}">
                <i class="bi bi-flag me-1"></i> Priorities
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.labels.index') }}">
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
            <span>Task Priorities</span>
            @can('tasks.settings.manage')
            <a href="{{ route('task-settings.priorities.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus me-1"></i> Add Priority
            </a>
            @endcan
        </div>

        <div class="px-3 pt-3 pb-2 border-bottom">
            <form method="GET" action="{{ route('task-settings.priorities.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="Name">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Default</label>
                    <select name="default" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="default" {{ request('default') === 'default' ? 'selected' : '' }}>Default</option>
                        <option value="non_default" {{ request('default') === 'non_default' ? 'selected' : '' }}>Non-default</option>
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

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Sort</label>
                    <div class="input-group input-group-sm">
                        <select name="sort" class="form-select form-select-sm">
                            <option value="">List order</option>
                            <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="level" {{ request('sort') === 'level' ? 'selected' : '' }}>Level</option>
                            <option value="tasks_count" {{ request('sort') === 'tasks_count' ? 'selected' : '' }}>Tasks</option>
                        </select>
                        <select name="dir" class="form-select form-select-sm" style="max-width: 110px;">
                            <option value="asc" {{ request('dir', 'asc') === 'asc' ? 'selected' : '' }}>Asc</option>
                            <option value="desc" {{ request('dir') === 'desc' ? 'selected' : '' }}>Desc</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-1 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Go
                    </button>
                </div>

                <div class="col-12">
                    <a href="{{ route('task-settings.priorities.index') }}" class="btn btn-sm btn-outline-secondary">
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
                        <th>Level</th>
                        <th>Color</th>
                        <th class="text-center">Tasks</th>
                        <th class="text-center">Default</th>
                        <th style="width: 120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($priorities as $priority)
                    <tr>
                        <td>
                            <span class="badge" style="background-color: {{ $priority->color }};">
                                {{ $priority->name }}
                            </span>
                        </td>
                        <td>{{ $priority->level }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 20px; height: 20px; background: {{ $priority->color }}; border-radius: 4px;"></div>
                                <code class="small">{{ $priority->color }}</code>
                            </div>
                        </td>
                        <td class="text-center">{{ $priority->tasks_count }}</td>
                        <td class="text-center">
                            @if($priority->is_default)
                            <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                            <i class="bi bi-circle text-muted"></i>
                            @endif
                        </td>
                        <td>
                            @can('tasks.settings.manage')
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('task-settings.priorities.edit', $priority) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if(!$priority->is_default && $priority->tasks_count == 0)
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="if(confirm('Delete this priority?')) document.getElementById('delete-{{ $priority->id }}').submit();">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="delete-{{ $priority->id }}" action="{{ route('task-settings.priorities.destroy', $priority) }}" method="POST" class="d-none">
                                    @csrf @method('DELETE')
                                </form>
                                @endif
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No priorities defined yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


