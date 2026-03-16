@php
    $query = request()->query();
@endphp

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('tasks.index', $query) }}"
       class="btn btn-sm {{ request()->routeIs('tasks.index') || request()->routeIs('tasks.my-tasks') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bi bi-list-task me-1"></i> List
    </a>
    <a href="{{ route('task-board.index', $query) }}"
       class="btn btn-sm {{ request()->routeIs('task-board.*') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bi bi-kanban me-1"></i> Board
    </a>
    <a href="{{ route('tasks.calendar', $query) }}"
       class="btn btn-sm {{ request()->routeIs('tasks.calendar') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bi bi-calendar3 me-1"></i> Calendar
    </a>
    <a href="{{ route('tasks.timeline', $query) }}"
       class="btn btn-sm {{ request()->routeIs('tasks.timeline') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bi bi-bar-chart-steps me-1"></i> Timeline
    </a>
    <a href="{{ route('tasks.workload', $query) }}"
       class="btn btn-sm {{ request()->routeIs('tasks.workload') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bi bi-people me-1"></i> Workload
    </a>
    <a href="{{ route('tasks.insights', $query) }}"
       class="btn btn-sm {{ request()->routeIs('tasks.insights') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="bi bi-graph-up-arrow me-1"></i> Insights
    </a>
</div>
