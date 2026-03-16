@extends('layouts.erp')

@section('title', 'Task Insights')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Task Insights</h1>
            <small class="text-muted">Throughput, cycle time, lead time, and team delivery trends.</small>
        </div>
    </div>

    @include('tasks.partials.planning-nav')

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg"><div class="card border-0 bg-warning bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Open</small><strong>{{ $summary['open_tasks'] }}</strong></div></div></div>
        <div class="col-6 col-lg"><div class="card border-0 bg-success bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Completed</small><strong>{{ $summary['completed_tasks'] }}</strong></div></div></div>
        <div class="col-6 col-lg"><div class="card border-0 bg-danger bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Overdue</small><strong>{{ $summary['overdue_tasks'] }}</strong></div></div></div>
        <div class="col-6 col-lg"><div class="card border-0 bg-info bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Avg Cycle</small><strong>{{ $summary['avg_cycle_hours'] }}h</strong></div></div></div>
        <div class="col-6 col-lg"><div class="card border-0 bg-primary bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Avg Lead</small><strong>{{ $summary['avg_lead_hours'] }}h</strong></div></div></div>
        <div class="col-6 col-lg"><div class="card border-0 bg-secondary bg-opacity-10 h-100"><div class="card-body py-2"><small class="text-muted d-block">Logged/Billable</small><strong>{{ $summary['logged_hours'] }}h / {{ $summary['billable_hours'] }}h</strong></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>14-Day Throughput</strong></div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-2 throughput-chart">
                        @foreach($throughputTrend as $point)
                        <div class="text-center flex-fill">
                            <div class="bg-primary rounded-top mx-auto" style="width: 100%; max-width: 36px; height: {{ max(12, $point['count'] * 22) }}px;"></div>
                            <div class="small fw-semibold mt-2">{{ $point['count'] }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">{{ $point['date'] }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong>Assignee Performance</strong></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Assignee</th>
                                <th>Completed</th>
                                <th>Avg Cycle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assigneePerformance as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['completed'] }}</td>
                                <td>{{ $row['avg_cycle_hours'] }}h</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No completed task data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
