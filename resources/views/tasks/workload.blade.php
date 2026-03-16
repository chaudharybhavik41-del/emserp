@extends('layouts.erp')

@section('title', 'Task Workload')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-people me-2"></i>Task Workload</h1>
            <small class="text-muted">Capacity planning by assignee using estimated vs logged effort.</small>
        </div>
    </div>

    @include('tasks.partials.planning-nav')

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg">
            <div class="card border-0 bg-primary bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Assignees</small><strong>{{ $summary['assignees'] }}</strong></div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-warning bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Open Tasks</small><strong>{{ $summary['open_tasks'] }}</strong></div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-danger bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Overdue</small><strong>{{ $summary['overdue_tasks'] }}</strong></div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-info bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Estimated Hours</small><strong>{{ $summary['estimated_hours'] }}</strong></div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card border-0 bg-success bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Logged Hours</small><strong>{{ $summary['logged_hours'] }}</strong></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Assignee</th>
                            <th>Load</th>
                            <th>Open</th>
                            <th>Overdue</th>
                            <th>Estimated</th>
                            <th>Logged</th>
                            <th>Remaining</th>
                            <th>Next Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $group)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $group['assignee']?->name ?? 'Unassigned' }}</div>
                                    <div class="small text-muted">{{ $group['tasks']->count() }} total tasks</div>
                                </td>
                                <td>
                                    <span class="badge {{ $group['load_status'] === 'high' ? 'bg-danger' : ($group['load_status'] === 'medium' ? 'bg-warning text-dark' : 'bg-success') }}">
                                        {{ ucfirst($group['load_status']) }}
                                    </span>
                                </td>
                                <td>{{ $group['open_count'] }}</td>
                                <td>{{ $group['overdue_count'] }}</td>
                                <td>{{ $group['estimated_hours'] }}h</td>
                                <td>{{ $group['logged_hours'] }}h</td>
                                <td>{{ $group['remaining_hours'] }}h</td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @foreach($group['tasks']->take(3) as $task)
                                            <a href="{{ route('tasks.show', $task) }}" class="small text-decoration-none">{{ $task->title }}</a>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No workload data matches the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
