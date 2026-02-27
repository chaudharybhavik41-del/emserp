@extends('layouts.erp')

@section('title', $taskList->name . ' - Board')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                @if($taskList->icon)
                <i class="{{ $taskList->icon }} me-2" style="color: {{ $taskList->color }}"></i>
                @else
                <i class="bi bi-kanban me-2" style="color: {{ $taskList->color }}"></i>
                @endif
                {{ $taskList->name }} - Board View
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('task-lists.show', $taskList) }}" class="btn btn-outline-secondary">
                <i class="bi bi-list-ul me-1"></i> List View
            </a>
            <a href="{{ route('tasks.create', ['list' => $taskList->id]) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Task
            </a>
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
                        <a href="{{ route('tasks.create', ['list' => $taskList->id, 'status' => $status->id]) }}" 
                           class="btn btn-sm btn-link text-muted p-0">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>

                {{-- Column Body --}}
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

                        @if($task->labels->count() > 0)
                        <div class="mb-2">
                            @foreach($task->labels->take(3) as $label)
                            <span class="badge" style="background-color: {{ $label->color }}; font-size: 10px;">{{ $label->name }}</span>
                            @endforeach
                        </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <div>
                                @if($task->assignee)
                                <span class="avatar avatar-xs" title="{{ $task->assignee->name }}">
                                    <span class="avatar-initial rounded-circle bg-primary" style="width: 24px; height: 24px; font-size: 10px; display: flex; align-items: center; justify-content: center;">
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
                            </div>
                        </div>
                    </div>
                    @endforeach

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

@push('styles')
<style>
.kanban-board { overflow-x: auto; }
.kanban-column { min-width: 280px; max-width: 280px; background: #f8f9fa; border-radius: 8px; display: flex; flex-direction: column; }
.kanban-column-header { padding: 12px; background: white; border-radius: 8px 8px 0 0; position: sticky; top: 0; z-index: 1; }
.kanban-column-body { flex: 1; padding: 8px; min-height: 200px; overflow-y: auto; max-height: calc(70vh - 60px); }
.kanban-card { background: white; border-radius: 6px; padding: 12px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); cursor: grab; transition: box-shadow 0.2s, transform 0.2s; }
.kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.kanban-card.dragging { opacity: 0.5; transform: rotate(3deg); }
.kanban-column-body.drag-over { background: #e3f2fd; }
.kanban-empty { border: 2px dashed #dee2e6; border-radius: 6px; margin: 8px 0; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
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
                this.appendChild(draggedCard);
                
                fetch('{{ route("task-board.move") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ task_id: taskId, status_id: newStatusId })
                }).then(r => r.json()).then(data => {
                    if (!data.success) location.reload();
                    document.querySelectorAll('.kanban-column').forEach(col => {
                        const count = col.querySelector('.kanban-column-body').querySelectorAll('.kanban-card').length;
                        col.querySelector('.badge').textContent = count;
                    });
                });
            }
        });
    });
});
</script>
@endpush
@endsection
