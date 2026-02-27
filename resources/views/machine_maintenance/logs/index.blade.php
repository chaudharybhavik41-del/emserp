@extends('layouts.erp')

@section('title', 'Maintenance Logs')

@section('content')
<div class="container">
    <h4 class="mb-4">Maintenance Logs</h4>

    @can('machinery.maintenance_log.create')
        <a href="{{ route('maintenance.logs.create') }}" class="btn btn-primary mb-3">+ New Log</a>
    @endcan

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 12%;">Log No</th>
                    <th style="width: 22%;">Machine</th>
                    <th>Plan</th>
                    <th style="width: 12%;">Type</th>
                    <th style="width: 12%;">Scheduled</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 12%;">Total Cost</th>
                    <th style="width: 12%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $log->log_number }}</div>
                            <div class="text-muted small">{{ optional($log->created_at)->format('Y-m-d') }}</div>
                        </td>
                        <td>
                            {{ $log->machine->name ?? '-' }}
                            <div class="text-muted small">{{ $log->machine->code ?? '' }}</div>
                        </td>
                        <td>
                            @if($log->plan)
                                <div class="fw-semibold">{{ $log->plan->plan_name }}</div>
                                <div class="text-muted small">{{ $log->plan->plan_code }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-capitalize">{{ $log->maintenance_type }}</td>
                        <td>{{ optional($log->scheduled_date)->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @php
                                $badge = match($log->status) {
                                    'completed' => 'bg-success',
                                    'in_progress' => 'bg-warning text-dark',
                                    'cancelled' => 'bg-secondary',
                                    'deferred' => 'bg-info text-dark',
                                    default => 'bg-primary',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_',' ', $log->status)) }}</span>
                        </td>
                        <td class="text-end">{{ number_format((float)($log->total_cost ?? 0), 2) }}</td>
                        <td>
                            <a href="{{ route('maintenance.logs.show', $log) }}" class="btn btn-sm btn-info">View</a>
                            @can('machinery.maintenance_log.update')
                                <a href="{{ route('maintenance.logs.edit', $log) }}" class="btn btn-sm btn-warning">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No logs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
