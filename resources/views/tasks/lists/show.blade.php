@extends('layouts.erp')

@section('title', $taskList->name)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('task-lists.index') }}">Task Lists</a></li>
                    @if($taskList->parent)
                    <li class="breadcrumb-item"><a href="{{ route('task-lists.show', $taskList->parent) }}">{{ $taskList->parent->name }}</a></li>
                    @endif
                    <li class="breadcrumb-item active">{{ $taskList->name }}</li>
                </ol>
            </nav>
            <h1 class="h4 mb-0">
                @if($taskList->icon)
                <i class="{{ $taskList->icon }} me-2" style="color: {{ $taskList->color }}"></i>
                @else
                <i class="bi bi-folder-fill me-2" style="color: {{ $taskList->color }}"></i>
                @endif
                {{ $taskList->name }}
                @if($taskList->project)
                <span class="badge bg-secondary ms-2">{{ $taskList->project->code }}</span>
                @endif
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('task-lists.board', $taskList) }}" class="btn btn-outline-primary">
                <i class="bi bi-kanban me-1"></i> Board View
            </a>
            <a href="{{ route('tasks.create', ['list' => $taskList->id]) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Task
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('task-lists.edit', $taskList) }}">
                        <i class="bi bi-pencil me-2"></i> Edit List
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" 
                           onclick="if(confirm('Archive this list?')) document.getElementById('archive-form').submit();">
                        <i class="bi bi-archive me-2"></i> Archive List
                    </a></li>
                </ul>
            </div>
            <form id="archive-form" action="{{ route('task-lists.archive', $taskList) }}" method="POST" class="d-none">
                @csrf
            </form>
        </div>
    </div>

    {{-- Description --}}
    @if($taskList->description)
    <div class="alert alert-light mb-3">
        {{ $taskList->description }}
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Total Tasks</div>
                            <div class="h5 mb-0">{{ $taskList->tasks_count }}</div>
                        </div>
                        <i class="bi bi-list-task fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Open</div>
                            <div class="h5 mb-0">{{ $taskList->open_tasks_count }}</div>
                        </div>
                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Completed</div>
                            <div class="h5 mb-0">{{ $taskList->tasks_count - $taskList->open_tasks_count }}</div>
                        </div>
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-0">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Progress</div>
                            <div class="h5 mb-0">{{ $taskList->progress_percent }}%</div>
                        </div>
                        <i class="bi bi-pie-chart fs-4 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="q" class="form-control form-control-sm" 
                           placeholder="Search tasks..." value="{{ request('q') }}" style="width: 180px;">
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                        <option value="{{ $status->id }}" {{ request('status') == $status->id ? 'selected' : '' }}>
                            {{ $status->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        @foreach($priorities as $priority)
                        <option value="{{ $priority->id }}" {{ request('priority') == $priority->id ? 'selected' : '' }}>
                            {{ $priority->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="assignee" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Assignees</option>
                        <option value="me" {{ request('assignee') === 'me' ? 'selected' : '' }}>Assigned to Me</option>
                        <option value="unassigned" {{ request('assignee') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('assignee') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    @if(request()->hasAny(['q', 'status', 'priority', 'assignee']))
                    <a href="{{ route('task-lists.show', $taskList) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x"></i> Clear
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Tasks Table --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll">
                        </th>
                        <th>Task</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 100px;">Priority</th>
                        <th style="width: 140px;">Assignee</th>
                        <th style="width: 100px;">Due Date</th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input task-checkbox" value="{{ $task->id }}">
                        </td>
                        <td>
                            <div>
                                <a href="{{ route('tasks.show', $task) }}" class="text-decoration-none fw-medium">
                                    {{ $task->title }}
                                </a>
                                <span class="text-muted small ms-2">{{ $task->task_number }}</span>
                            </div>
                            @if($task->labels->count() > 0)
                            <div class="mt-1">
                                @foreach($task->labels->take(3) as $label)
                                <span class="badge" style="background-color: {{ $label->color }}; font-size: 10px;">{{ $label->name }}</span>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        <td>
                            @if($task->status)
                            <span class="badge" style="background-color: {{ $task->status->color }};">
                                {{ $task->status->name }}
                            </span>
                            @endif
                        </td>
                        <td>
                            @if($task->priority)
                            <span class="badge" style="background-color: {{ $task->priority->color }};">
                                {{ $task->priority->name }}
                            </span>
                            @endif
                        </td>
                        <td>
                            @if($task->assignee)
                            <span class="d-flex align-items-center gap-1">
                                <span class="avatar avatar-xs">
                                    <span class="avatar-initial rounded-circle bg-primary" style="width: 24px; height: 24px; font-size: 10px; display: flex; align-items: center; justify-content: center;">
                                        {{ substr($task->assignee->name, 0, 1) }}
                                    </span>
                                </span>
                                <span class="small">{{ $task->assignee->name }}</span>
                            </span>
                            @else
                            <span class="text-muted small">Unassigned</span>
                            @endif
                        </td>
                        <td>
                            @if($task->due_date)
                            <span class="{{ $task->isOverdue() ? 'text-danger fw-bold' : ($task->isDueToday() ? 'text-warning' : 'text-muted') }} small">
                                {{ $task->due_date->format('M d') }}
                            </span>
                            @else
                            <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('tasks.show', $task) }}">
                                        <i class="bi bi-eye me-2"></i> View
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('tasks.edit', $task) }}">
                                        <i class="bi bi-pencil me-2"></i> Edit
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" 
                                           onclick="if(confirm('Delete this task?')) { document.getElementById('delete-task-{{ $task->id }}').submit(); }">
                                        <i class="bi bi-trash me-2"></i> Delete
                                    </a></li>
                                </ul>
                            </div>
                            <form id="delete-task-{{ $task->id }}" action="{{ route('tasks.destroy', $task) }}" method="POST" class="d-none">
                                @csrf @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bi bi-inbox display-6 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-2">No tasks in this list yet</p>
                            <a href="{{ route('tasks.create', ['list' => $taskList->id]) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus me-1"></i> Add First Task
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
        <div class="card-footer">
            {{ $tasks->links() }}
        </div>
        @endif
    </div>

    {{-- Child Lists --}}
    @if($taskList->children->count() > 0)
    <div class="card mt-3">
        <div class="card-header">
            <strong>Sub-Lists</strong>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($taskList->children as $child)
                <div class="col-md-4 mb-2">
                    <a href="{{ route('task-lists.show', $child) }}" class="card text-decoration-none h-100" style="border-left: 4px solid {{ $child->color }};">
                        <div class="card-body py-2">
                            <div class="fw-medium">
                                @if($child->icon)<i class="{{ $child->icon }} me-1"></i>@endif
                                {{ $child->name }}
                            </div>
                            <small class="text-muted">{{ $child->tasks_count }} tasks</small>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.task-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
@endsection
