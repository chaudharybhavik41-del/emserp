@extends('layouts.erp')

@section('title', 'Task Timeline')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Task Timeline</h1>
            <small class="text-muted">Gantt-style sequencing for scheduled work.</small>
        </div>
        <div class="small text-muted">
            {{ $rangeStart->format('d M Y') }} to {{ $rangeEnd->format('d M Y') }}
        </div>
    </div>

    @include('tasks.partials.planning-nav')
    @include('tasks.partials.saved-views', ['currentViewType' => 'gantt'])

    <div class="card">
        <div class="card-body p-0">
            <div class="timeline-table">
                <div class="timeline-row timeline-header">
                    <div class="timeline-meta">Task</div>
                    <div class="timeline-scale" style="grid-template-columns: repeat({{ $weeks->count() }}, minmax(80px, 1fr));">
                        @foreach($weeks as $weekStart)
                            <div class="timeline-week">{{ $weekStart->format('d M') }}</div>
                        @endforeach
                    </div>
                </div>

                @forelse($tasks as $task)
                    @php
                        $taskStart = $task->start_date ?? $task->due_date ?? $rangeStart;
                        $taskEnd = $task->due_date ?? $task->start_date ?? $taskStart;
                        $offset = max(0, $rangeStart->diffInDays($taskStart, false));
                        $span = max(1, $taskStart->diffInDays($taskEnd) + 1);
                        $left = ($offset / $rangeDays) * 100;
                        $width = min(100 - $left, ($span / $rangeDays) * 100);
                    @endphp
                    <div class="timeline-row">
                        <div class="timeline-meta">
                            <a href="#"
                               class="fw-medium text-decoration-none"
                               data-bs-toggle="modal"
                               data-bs-target="#timelineScheduleModal"
                               data-task-id="{{ $task->id }}"
                               data-task-title="{{ $task->title }}"
                               data-task-view-url="{{ route('tasks.show', $task) }}"
                               data-task-start="{{ $task->start_date?->format('Y-m-d') }}"
                               data-task-due="{{ $task->due_date?->format('Y-m-d') }}">{{ $task->title }}</a>
                            <div class="small text-muted">
                                {{ $task->task_number }} · {{ $task->taskList->name ?? 'No list' }}
                            </div>
                        </div>
                        <div class="timeline-scale">
                            <div class="timeline-bar-wrap">
                                <div class="timeline-bar" style="left: {{ $left }}%; width: {{ $width }}%; background-color: {{ $task->status->color }};">
                                    <span>{{ $task->start_date?->format('d M') ?? $task->due_date?->format('d M') }} - {{ $task->due_date?->format('d M') ?? $task->start_date?->format('d M') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">No scheduled tasks match the current filters.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="timelineScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="timelineScheduleForm">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Update Timeline Dates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="fw-semibold" id="timelineScheduleTitle"></div>
                        <a href="#" id="timelineScheduleTaskLink" class="small text-decoration-none">Open task</a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="timelineStartDate" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="timelineDueDate" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Dates</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.timeline-table {
    min-width: 980px;
}
.timeline-row {
    display: grid;
    grid-template-columns: 300px 1fr;
    border-top: 1px solid var(--bs-border-color);
}
.timeline-header {
    background: rgba(108, 117, 125, 0.06);
    font-weight: 600;
}
.timeline-meta {
    padding: 0.85rem 1rem;
    border-right: 1px solid var(--bs-border-color);
}
.timeline-scale {
    position: relative;
    padding: 0.85rem 1rem;
}
.timeline-header .timeline-scale {
    display: grid;
}
.timeline-week {
    font-size: 0.78rem;
    color: var(--bs-secondary-color);
}
.timeline-bar-wrap {
    position: relative;
    height: 26px;
    background-image: linear-gradient(to right, rgba(108,117,125,0.08) 1px, transparent 1px);
    background-size: calc(100% / {{ max(1, $weeks->count()) }}) 100%;
}
.timeline-bar {
    position: absolute;
    top: 1px;
    height: 24px;
    border-radius: 999px;
    color: #fff;
    font-size: 0.74rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0.2rem 0.65rem;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scheduleModal = document.getElementById('timelineScheduleModal');
    if (!scheduleModal) return;

    scheduleModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        document.getElementById('timelineScheduleTitle').textContent = trigger.dataset.taskTitle || '';
        document.getElementById('timelineScheduleTaskLink').href = trigger.dataset.taskViewUrl || '#';
        document.getElementById('timelineStartDate').value = trigger.dataset.taskStart || '';
        document.getElementById('timelineDueDate').value = trigger.dataset.taskDue || '';
        document.getElementById('timelineScheduleForm').action = `/tasks/${trigger.dataset.taskId}/schedule`;
    });
});
</script>
@endpush
@endsection
