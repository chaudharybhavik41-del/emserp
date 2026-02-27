@extends('layouts.erp')

@section('title', 'Task Board')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-kanban me-2"></i>Task Board
                @if($currentList)
                <span class="text-muted">- {{ $currentList->name }}</span>
                @elseif($currentProject)
                <span class="text-muted">- {{ $currentProject->code }}</span>
                @endif
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tasks.index', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-list-ul me-1"></i> List View
            </a>
            @can('tasks.create')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickCreateModal">
                <i class="bi bi-plus-lg me-1"></i> New Task
            </button>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('task-board.index') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <select name="list" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Lists</option>
                        @foreach($taskLists as $list)
                        <option value="{{ $list->id }}" {{ request('list') == $list->id ? 'selected' : '' }}>
                            {{ $list->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="project" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                            {{ $project->code }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="bom" class="form-select form-select-sm" onchange="this.form.submit()" {{ $boms->isEmpty() ? 'disabled' : '' }}>
                        <option value="">{{ $boms->isEmpty() ? 'Select Project for BOMs' : 'All BOMs' }}</option>
                        @foreach($boms as $bom)
                        <option value="{{ $bom->id }}" {{ request('bom') == $bom->id ? 'selected' : '' }}>
                            {{ $bom->bom_number }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="assignee" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Assignees</option>
                        <option value="me" {{ request('assignee') === 'me' ? 'selected' : '' }}>My Tasks</option>
                        <option value="unassigned" {{ request('assignee') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('assignee') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." 
                           value="{{ request('q') }}" style="width: 150px;">
                </div>
                <div class="col-auto">
                    @if(request()->hasAny(['list', 'project', 'bom', 'assignee', 'q']))
                    <a href="{{ route('task-board.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x"></i> Clear
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Overview --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-primary bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Total</small>
                    <strong class="h5 mb-0">{{ $boardStats['total'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-warning bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Open</small>
                    <strong class="h5 mb-0">{{ $boardStats['open'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-danger bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Overdue</small>
                    <strong class="h5 mb-0">{{ $boardStats['overdue'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 bg-secondary bg-opacity-10 h-100">
                <div class="card-body py-2">
                    <small class="text-muted d-block">Unassigned</small>
                    <strong class="h5 mb-0">{{ $boardStats['unassigned'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Kanban Board --}}
    <div class="kanban-board">
        <div class="d-flex gap-3 overflow-auto pb-3" style="min-height: 70vh;">
            @foreach($statuses as $status)
            <div class="kanban-column" data-status-id="{{ $status->id }}">
                {{-- Column Header --}}
                <div class="kanban-column-header" style="border-top: 3px solid {{ $status->color }};">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $status->name }}</strong>
                            <span class="badge bg-secondary ms-1">{{ ($tasksByStatus[$status->id] ?? collect())->count() }}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-muted p-0" 
                                onclick="openQuickCreate({{ $status->id }})">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Column Body (Droppable) --}}
                <div class="kanban-column-body" data-status-id="{{ $status->id }}">
                    @foreach($tasksByStatus[$status->id] ?? [] as $task)
                    <div class="kanban-card" data-task-id="{{ $task->id }}" draggable="true">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="small text-muted">{{ $task->task_number }}</span>
                            @if($task->priority)
                            <span class="badge" style="background-color: {{ $task->priority->color }}; font-size: 10px;">
                                {{ $task->priority->name }}
                            </span>
                            @endif
                        </div>
                        
                        <a href="{{ route('tasks.show', $task) }}" class="text-decoration-none text-dark">
                            <div class="fw-medium mb-2">{{ Str::limit($task->title, 60) }}</div>
                        </a>

                        @if($task->progress_percent > 0)
                        <div class="mb-2">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar {{ $task->progress_percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $task->progress_percent }}%"></div>
                            </div>
                            <div class="small text-muted mt-1">{{ $task->progress_percent }}% complete</div>
                        </div>
                        @endif

                        {{-- Labels --}}
                        @if($task->labels->count() > 0)
                        <div class="mb-2">
                            @foreach($task->labels->take(3) as $label)
                            <span class="badge" style="background-color: {{ $label->color }}; font-size: 10px;">
                                {{ $label->name }}
                            </span>
                            @endforeach
                        </div>
                        @endif

                        {{-- Subtasks progress --}}
                        @if($task->children->count() > 0)
                        <div class="mb-2">
                            <div class="d-flex align-items-center small text-muted">
                                <i class="bi bi-list-task me-1"></i>
                                {{ $task->completed_subtask_count }}/{{ $task->subtask_count }}
                            </div>
                        </div>
                        @endif

                        {{-- Footer --}}
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <div>
                                @if($task->assignee)
                                <span class="avatar avatar-xs" title="{{ $task->assignee->name }}">
                                    <span class="avatar-initial rounded-circle bg-primary" style="width: 24px; height: 24px; font-size: 10px;">
                                        {{ substr($task->assignee->name, 0, 1) }}
                                    </span>
                                </span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 small text-muted">
                                @if($task->due_date)
                                <span class="{{ $task->isOverdue() ? 'text-danger fw-bold' : '' }}" title="Due date">
                                    <i class="bi bi-calendar me-1"></i>{{ $task->due_date->format('M d') }}
                                </span>
                                @endif
                                @if($task->comments_count ?? 0)
                                <span title="Comments"><i class="bi bi-chat"></i> {{ $task->comments_count }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Empty state --}}
                    @if(($tasksByStatus[$status->id] ?? collect())->isEmpty())
                    <div class="text-center text-muted py-4 kanban-empty">
                        <i class="bi bi-inbox d-block mb-2"></i>
                        <small>No tasks</small>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Quick Create Modal --}}
<div class="modal fade" id="quickCreateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="quickCreateForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Quick Add Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="status_id" id="quickStatusId" value="{{ $statuses->first()?->id }}">
                    
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Task title...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Task List <span class="text-danger">*</span></label>
                        <select name="task_list_id" class="form-select" required>
                            @foreach($taskLists as $list)
                            <option value="{{ $list->id }}" {{ request('list') == $list->id ? 'selected' : '' }}>
                                {{ $list->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Priority</label>
                            <select name="priority_id" class="form-select">
                                <option value="">None</option>
                                @foreach($priorities as $priority)
                                <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Assignee</label>
                            <select name="assignee_id" class="form-select">
                                <option value="">Unassigned</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.kanban-board {
    overflow-x: auto;
}

.kanban-column {
    min-width: 280px;
    max-width: 280px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.kanban-column-header {
    padding: 12px;
    background: white;
    border-radius: 8px 8px 0 0;
    position: sticky;
    top: 0;
    z-index: 1;
}

.kanban-column-body {
    flex: 1;
    padding: 8px;
    min-height: 200px;
    overflow-y: auto;
    max-height: calc(70vh - 60px);
}

.kanban-card {
    background: white;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    cursor: grab;
    transition: box-shadow 0.2s, transform 0.2s;
}

.kanban-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.kanban-card.dragging {
    opacity: 0.5;
    transform: rotate(3deg);
}

.kanban-column-body.drag-over {
    background: #e3f2fd;
}

.kanban-empty {
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    margin: 8px 0;
}

.avatar-initial {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Drag and drop functionality
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column-body');
    
    let draggedCard = null;

    cards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            columns.forEach(col => col.classList.remove('drag-over'));
        });
    });

    columns.forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        column.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        column.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (draggedCard) {
                const newStatusId = this.dataset.statusId;
                const taskId = draggedCard.dataset.taskId;
                
                // Get all cards in the column for position calculation
                const columnCards = Array.from(this.querySelectorAll('.kanban-card'));
                const position = columnCards.length;
                
                // Move card visually
                this.appendChild(draggedCard);
                
                // Update via AJAX
                fetch('{{ route("task-board.move") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        status_id: newStatusId,
                        position: position
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Error moving task');
                        location.reload();
                    }
                    // Update column counts
                    updateColumnCounts();
                })
                .catch(() => location.reload());
            }
        });
    });

    function updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(col => {
            const count = col.querySelector('.kanban-column-body').querySelectorAll('.kanban-card').length;
            col.querySelector('.badge').textContent = count;
        });
    }

    // Quick create
    window.openQuickCreate = function(statusId) {
        document.getElementById('quickStatusId').value = statusId;
        new bootstrap.Modal(document.getElementById('quickCreateModal')).show();
    };

    document.getElementById('quickCreateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('{{ route("task-board.quick-create") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error creating task');
            }
        });
    });
});
</script>
@endpush
@endsection
