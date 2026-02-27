@extends('layouts.erp')

@section('title', 'Tasks')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-check2-square me-2"></i>Tasks
            </h1>
            <small class="text-muted">Manage fabrication tasks and activities</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('task-board.index', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-kanban me-1"></i> Board View
            </a>
            @can('tasks.create')
            <a href="{{ route('tasks.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Task
            </a>
            @endcan
        </div>
    </div>

    {{-- Overview --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg">
            <div class="card border-0 bg-primary bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Total</small>
                    <strong class="h5 mb-0">{{ $stats['total'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-warning bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Open</small>
                    <strong class="h5 mb-0">{{ $stats['open'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-success bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Completed</small>
                    <strong class="h5 mb-0">{{ $stats['completed'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-danger bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Overdue</small>
                    <strong class="h5 mb-0">{{ $stats['overdue'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-info bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Due Today</small>
                    <strong class="h5 mb-0">{{ $stats['due_today'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('tasks.index') }}" class="row g-2 align-items-end">
                {{-- Search --}}
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm" 
                           placeholder="Search tasks..." value="{{ request('q') }}">
                </div>

                {{-- Task List --}}
                <div class="col-md-2">
                    <select name="list" class="form-select form-select-sm">
                        <option value="">All Lists</option>
                        @foreach($taskLists as $list)
                        <option value="{{ $list->id }}" {{ request('list') == $list->id ? 'selected' : '' }}>
                            {{ $list->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                        <option value="{{ $status->id }}" {{ request('status') == $status->id ? 'selected' : '' }}>
                            {{ $status->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Priority --}}
                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All Priorities</option>
                        @foreach($priorities as $priority)
                        <option value="{{ $priority->id }}" {{ request('priority') == $priority->id ? 'selected' : '' }}>
                            {{ $priority->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Assignee --}}
                <div class="col-md-2">
                    <select name="assignee" class="form-select form-select-sm">
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

                {{-- Project --}}
                <div class="col-md-2">
                    <select name="project" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                            {{ $project->code }} - {{ $project->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- BOM --}}
                <div class="col-md-2">
                    <select name="bom" class="form-select form-select-sm" {{ $boms->isEmpty() ? 'disabled' : '' }}>
                        <option value="">{{ $boms->isEmpty() ? 'Select Project for BOMs' : 'All BOMs' }}</option>
                        @foreach($boms as $bom)
                        <option value="{{ $bom->id }}" {{ request('bom') == $bom->id ? 'selected' : '' }}>
                            {{ $bom->bom_number }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if(request('q') || request('list') || request('status') || request('priority') || request('assignee') || request('project') || request('bom'))
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted">Active filters:</span>
        @if(request('q'))
        <span class="badge text-bg-light">Search: {{ request('q') }}</span>
        @endif
        @if(request('list'))
        <span class="badge text-bg-light">List: {{ optional($taskLists->firstWhere('id', (int) request('list')))->name ?? request('list') }}</span>
        @endif
        @if(request('status'))
        <span class="badge text-bg-light">Status: {{ optional($statuses->firstWhere('id', (int) request('status')))->name ?? request('status') }}</span>
        @endif
        @if(request('priority'))
        <span class="badge text-bg-light">Priority: {{ optional($priorities->firstWhere('id', (int) request('priority')))->name ?? request('priority') }}</span>
        @endif
        @if(request('assignee'))
        <span class="badge text-bg-light">
            Assignee:
            {{ request('assignee') === 'me'
                ? 'Me'
                : (request('assignee') === 'unassigned'
                    ? 'Unassigned'
                    : (optional($users->firstWhere('id', (int) request('assignee')))->name ?? request('assignee'))) }}
        </span>
        @endif
        @if(request('project'))
        <span class="badge text-bg-light">
            Project: {{ optional($projects->firstWhere('id', (int) request('project')))->code ?? request('project') }}
        </span>
        @endif
        @if(request('bom'))
        <span class="badge text-bg-light">
            BOM: {{ optional($boms->firstWhere('id', (int) request('bom')))->bom_number ?? request('bom') }}
        </span>
        @endif
        <a href="{{ route('tasks.index') }}" class="btn btn-link btn-sm p-0 text-decoration-none">Clear all</a>
    </div>
    @endif

    {{-- Quick Filters --}}
    <div class="mb-3">
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('tasks.index') }}" 
               class="btn {{ !request()->hasAny(['overdue', 'due_today', 'due_this_week']) ? 'btn-primary' : 'btn-outline-primary' }}">
                All
            </a>
            <a href="{{ route('tasks.index', ['overdue' => 1]) }}" 
               class="btn {{ request('overdue') ? 'btn-danger' : 'btn-outline-danger' }}">
                <i class="bi bi-exclamation-triangle me-1"></i> Overdue
            </a>
            <a href="{{ route('tasks.index', ['due_today' => 1]) }}" 
               class="btn {{ request('due_today') ? 'btn-warning' : 'btn-outline-warning' }}">
                <i class="bi bi-calendar-day me-1"></i> Due Today
            </a>
            <a href="{{ route('tasks.index', ['due_this_week' => 1]) }}" 
               class="btn {{ request('due_this_week') ? 'btn-info' : 'btn-outline-info' }}">
                <i class="bi bi-calendar-week me-1"></i> This Week
            </a>
            <a href="{{ route('tasks.my-tasks') }}" 
               class="btn {{ request()->routeIs('tasks.my-tasks') ? 'btn-success' : 'btn-outline-success' }}">
                <i class="bi bi-person me-1"></i> My Tasks
            </a>
        </div>
    </div>

    {{-- Tasks Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll">
                            </th>
                            <th>Task</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 100px;">Priority</th>
                            <th style="width: 150px;">Assignee</th>
                            <th style="width: 100px;">Due Date</th>
                            <th style="width: 80px;">Progress</th>
                            <th style="width: 100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                        <tr class="{{ $task->isOverdue() ? 'table-danger' : '' }}">
                            <td>
                                <input type="checkbox" class="form-check-input task-checkbox" value="{{ $task->id }}">
                            </td>
                            <td>
                                <div class="d-flex align-items-start">
                                    <div>
                                        <a href="{{ route('tasks.show', $task) }}" class="text-decoration-none fw-medium">
                                            {{ $task->title }}
                                        </a>
                                        <div class="small text-muted">
                                            <span class="me-2">{{ $task->task_number }}</span>
                                            @if($task->taskList)
                                            <span class="badge bg-light text-dark me-1">{{ $task->taskList->name }}</span>
                                            @endif
                                            @if($task->project)
                                            <span class="badge bg-secondary me-1">{{ $task->project->code }}</span>
                                            @endif
                                            @foreach($task->labels->take(3) as $label)
                                            <span class="badge" style="background-color: {{ $label->color }}; color: {{ $label->text_color }}">
                                                {{ $label->name }}
                                            </span>
                                            @endforeach
                                        </div>
                                        @if($task->subtask_count > 0)
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-list-task me-1"></i>
                                            {{ $task->completed_subtask_count }}/{{ $task->subtask_count }} subtasks
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background-color: {{ $task->status->color }}">
                                    {{ $task->status->name }}
                                </span>
                            </td>
                            <td>
                                @if($task->priority)
                                <span class="badge" style="background-color: {{ $task->priority->color }}">
                                    <i class="{{ $task->priority->icon }} me-1"></i>
                                    {{ $task->priority->name }}
                                </span>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($task->assignee)
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2">
                                        <span class="avatar-initial rounded-circle bg-primary">
                                            {{ substr($task->assignee->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <span class="small">{{ $task->assignee->name }}</span>
                                </div>
                                @else
                                <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                @if($task->due_date)
                                <span class="{{ $task->isOverdue() ? 'text-danger fw-bold' : ($task->isDueToday() ? 'text-warning' : '') }}">
                                    {{ $task->due_date->format('d M Y') }}
                                </span>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="progress" style="height: 6px; width: 60px;">
                                    <div class="progress-bar {{ $task->progress_percent == 100 ? 'bg-success' : 'bg-primary' }}" 
                                         style="width: {{ $task->progress_percent }}%"></div>
                                </div>
                                <small class="text-muted">{{ $task->progress_percent }}%</small>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('tasks.show', $task) }}">
                                            <i class="bi bi-eye me-2"></i> View
                                        </a></li>
                                        <li><a class="dropdown-item" href="{{ route('tasks.edit', $task) }}">
                                            <i class="bi bi-pencil me-2"></i> Edit
                                        </a></li>
                                        <li><a class="dropdown-item" href="{{ route('tasks.duplicate', $task) }}" 
                                               onclick="event.preventDefault(); document.getElementById('duplicate-{{ $task->id }}').submit();">
                                            <i class="bi bi-copy me-2"></i> Duplicate
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" 
                                               onclick="if(confirm('Delete this task?')) document.getElementById('delete-{{ $task->id }}').submit();">
                                            <i class="bi bi-trash me-2"></i> Delete
                                        </a></li>
                                    </ul>
                                </div>
                                <form id="duplicate-{{ $task->id }}" action="{{ route('tasks.duplicate', $task) }}" method="POST" class="d-none">@csrf</form>
                                <form id="delete-{{ $task->id }}" action="{{ route('tasks.destroy', $task) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                No tasks found. 
                                <a href="{{ route('tasks.create') }}">Create your first task</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($tasks->hasPages())
        <div class="card-footer">
            {{ $tasks->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Bulk Actions Bar --}}
<div id="bulkActionsBar" class="fixed-bottom bg-dark text-white py-2 d-none">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span id="selectedCount">0</span> tasks selected
            </div>
            <div class="d-flex gap-2">
                <select id="bulkStatus" class="form-select form-select-sm" style="width: auto;">
                    <option value="">Change Status</option>
                    @foreach($statuses as $status)
                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </select>
                <select id="bulkPriority" class="form-select form-select-sm" style="width: auto;">
                    <option value="">Change Priority</option>
                    @foreach($priorities as $priority)
                    <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                    @endforeach
                </select>
                <select id="bulkAssignee" class="form-select form-select-sm" style="width: auto;">
                    <option value="">Assign to</option>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="bulkArchive()">
                    <i class="bi bi-archive"></i> Archive
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.avatar-sm { width: 28px; height: 28px; }
.avatar-initial { 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    width: 28px; 
    height: 28px; 
    font-size: 12px; 
    color: white;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.task-checkbox');
    const bulkBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');

    function updateBulkBar() {
        const checked = document.querySelectorAll('.task-checkbox:checked');
        if (checked.length > 0) {
            bulkBar.classList.remove('d-none');
            selectedCount.textContent = checked.length;
        } else {
            bulkBar.classList.add('d-none');
        }
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkBar();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });

    // Bulk actions
    window.bulkUpdate = function(action, value) {
        const ids = Array.from(document.querySelectorAll('.task-checkbox:checked')).map(cb => cb.value);
        if (ids.length === 0) return;

        fetch('{{ route("tasks.bulk-update") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ task_ids: ids, action: action, value: value })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
    };

    document.getElementById('bulkStatus').addEventListener('change', function() {
        if (this.value) bulkUpdate('status', this.value);
    });
    document.getElementById('bulkPriority').addEventListener('change', function() {
        if (this.value) bulkUpdate('priority', this.value);
    });
    document.getElementById('bulkAssignee').addEventListener('change', function() {
        if (this.value) bulkUpdate('assignee', this.value);
    });

    window.bulkArchive = function() {
        if (confirm('Archive selected tasks?')) bulkUpdate('archive', null);
    };
    window.bulkDelete = function() {
        if (confirm('Delete selected tasks? This cannot be undone.')) bulkUpdate('delete', null);
    };
});
</script>
@endpush
@endsection
