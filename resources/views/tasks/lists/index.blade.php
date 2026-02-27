@extends('layouts.erp')

@section('title', 'Task Lists')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-folder me-2"></i>Task Lists
            </h1>
            <small class="text-muted">Organize tasks into lists and folders</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list-task me-1"></i> All Tasks
            </a>
            @can('tasks.list.create')
            <a href="{{ route('task-lists.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New List
            </a>
            @endcan
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75">Total Lists</div>
                            <div class="h4 mb-0">{{ $stats['total_lists'] }}</div>
                        </div>
                        <i class="bi bi-folder fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75">Total Tasks</div>
                            <div class="h4 mb-0">{{ $stats['total_tasks'] }}</div>
                        </div>
                        <i class="bi bi-check2-square fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75">Open Tasks</div>
                            <div class="h4 mb-0">{{ $stats['open_tasks'] }}</div>
                        </div>
                        <i class="bi bi-hourglass-split fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75">Overdue</div>
                            <div class="h4 mb-0">{{ $stats['overdue_tasks'] }}</div>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('task-lists.index') }}" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm" 
                           placeholder="Search lists..." value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="project" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                            {{ $project->code }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('task-lists.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Lists Grid --}}
    <div class="row">
        @forelse($taskLists as $list)
        <div class="col-md-4 col-lg-3 mb-3">
            <div class="card h-100 task-list-card" style="border-top: 4px solid {{ $list->color }};">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="card-title mb-1">
                                <a href="{{ route('task-lists.show', $list) }}" class="text-decoration-none text-dark">
                                    @if($list->icon)
                                    <i class="{{ $list->icon }} me-1" style="color: {{ $list->color }}"></i>
                                    @else
                                    <i class="bi bi-folder-fill me-1" style="color: {{ $list->color }}"></i>
                                    @endif
                                    {{ $list->name }}
                                </a>
                            </h5>
                            @if($list->project)
                            <span class="badge bg-secondary">{{ $list->project->code }}</span>
                            @endif
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('task-lists.show', $list) }}">
                                    <i class="bi bi-eye me-2"></i> View
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('task-lists.board', $list) }}">
                                    <i class="bi bi-kanban me-2"></i> Board
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('task-lists.edit', $list) }}">
                                    <i class="bi bi-pencil me-2"></i> Edit
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" 
                                       onclick="if(confirm('Delete this list?')) document.getElementById('delete-{{ $list->id }}').submit();">
                                    <i class="bi bi-trash me-2"></i> Delete
                                </a></li>
                            </ul>
                        </div>
                        <form id="delete-{{ $list->id }}" action="{{ route('task-lists.destroy', $list) }}" method="POST" class="d-none">
                            @csrf @method('DELETE')
                        </form>
                    </div>

                    @if($list->description)
                    <p class="card-text small text-muted mb-3">{{ Str::limit($list->description, 80) }}</p>
                    @endif

                    {{-- Progress --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Progress</span>
                            <span>{{ $list->progress_percent }}%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar {{ $list->progress_percent == 100 ? 'bg-success' : 'bg-primary' }}" 
                                 style="width: {{ $list->progress_percent }}%"></div>
                        </div>
                    </div>

                    {{-- Stats --}}
                    <div class="d-flex justify-content-between text-muted small">
                        <span><i class="bi bi-check2-square me-1"></i> {{ $list->tasks_count }} tasks</span>
                        <span>
                            @if($list->open_tasks_count > 0)
                            <span class="text-warning">{{ $list->open_tasks_count }} open</span>
                            @else
                            <span class="text-success">All done</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="card-footer bg-transparent border-0 pt-0">
                    <div class="d-flex justify-content-between align-items-center">
                        @if($list->owner)
                        <small class="text-muted">
                            <i class="bi bi-person me-1"></i> {{ $list->owner->name }}
                        </small>
                        @endif
                        <a href="{{ route('tasks.create', ['list' => $list->id]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus"></i> Add Task
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-folder-x display-4 text-muted d-block mb-3"></i>
                    <h5>No Task Lists Yet</h5>
                    <p class="text-muted">Create your first task list to start organizing tasks.</p>
                    <a href="{{ route('task-lists.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Create Task List
                    </a>
                </div>
            </div>
        </div>
        @endforelse
    </div>
</div>

@push('styles')
<style>
.task-list-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.task-list-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
</style>
@endpush
@endsection
