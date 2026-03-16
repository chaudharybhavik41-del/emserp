@extends('layouts.erp')

@section('title', 'Task Calendar')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-calendar3 me-2"></i>Task Calendar</h1>
            <small class="text-muted">Date-based planning for due work.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tasks.calendar', array_merge(request()->query(), ['month' => $month->copy()->subMonth()->format('Y-m')])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="btn btn-light btn-sm disabled">{{ $month->format('F Y') }}</span>
            <a href="{{ route('tasks.calendar', array_merge(request()->query(), ['month' => $month->copy()->addMonth()->format('Y-m')])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    @include('tasks.partials.planning-nav')
    @include('tasks.partials.saved-views', ['currentViewType' => 'calendar'])

    <div class="card">
        <div class="card-body p-0">
            <div class="calendar-grid border-top border-start">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="calendar-header fw-semibold small text-muted bg-light">{{ $weekday }}</div>
                @endforeach

                @foreach($calendarDays as $day)
                    @php
                        $dayTasks = $tasksByDay->get($day->toDateString(), collect());
                    @endphp
                    <div class="calendar-cell {{ $day->month !== $month->month ? 'calendar-cell-muted' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold">{{ $day->format('d') }}</span>
                            @if($dayTasks->count() > 0)
                                <span class="badge text-bg-light">{{ $dayTasks->count() }}</span>
                            @endif
                        </div>
                        <div class="d-flex flex-column gap-1">
                            @foreach($dayTasks->take(4) as $task)
                                <a href="#"
                                   class="calendar-task text-decoration-none"
                                   data-bs-toggle="modal"
                                   data-bs-target="#scheduleModal"
                                   data-task-id="{{ $task->id }}"
                                   data-task-title="{{ $task->title }}"
                                   data-task-view-url="{{ route('tasks.show', $task) }}"
                                   data-task-start="{{ $task->start_date?->format('Y-m-d') }}"
                                   data-task-due="{{ $task->due_date?->format('Y-m-d') }}">
                                    <span class="calendar-task-status" style="background-color: {{ $task->status->color }}"></span>
                                    <span class="text-truncate">{{ $task->title }}</span>
                                </a>
                            @endforeach
                            @if($dayTasks->count() > 4)
                                <span class="small text-muted">+{{ $dayTasks->count() - 4 }} more</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="scheduleForm">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Update Task Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="fw-semibold" id="scheduleTaskTitle"></div>
                        <a href="#" id="scheduleTaskLink" class="small text-decoration-none">Open task</a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="scheduleStartDate" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="scheduleDueDate" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
}
.calendar-header,
.calendar-cell {
    min-height: 130px;
    border-right: 1px solid var(--bs-border-color);
    border-bottom: 1px solid var(--bs-border-color);
    padding: 0.75rem;
}
.calendar-header {
    min-height: auto;
    padding: 0.5rem 0.75rem;
}
.calendar-cell-muted {
    background: rgba(108, 117, 125, 0.05);
}
.calendar-task {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: inherit;
    background: rgba(13, 110, 253, 0.06);
    border-radius: 0.35rem;
    padding: 0.25rem 0.4rem;
}
.calendar-task-status {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    flex: 0 0 auto;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scheduleModal = document.getElementById('scheduleModal');
    if (!scheduleModal) return;

    scheduleModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        document.getElementById('scheduleTaskTitle').textContent = trigger.dataset.taskTitle || '';
        document.getElementById('scheduleTaskLink').href = trigger.dataset.taskViewUrl || '#';
        document.getElementById('scheduleStartDate').value = trigger.dataset.taskStart || '';
        document.getElementById('scheduleDueDate').value = trigger.dataset.taskDue || '';
        document.getElementById('scheduleForm').action = `/tasks/${trigger.dataset.taskId}/schedule`;
    });
});
</script>
@endpush
@endsection
