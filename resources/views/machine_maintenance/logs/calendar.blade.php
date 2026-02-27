@extends('layouts.erp')

@section('title', 'Maintenance Calendar')

@section('content')
<div class="container">
    <h4 class="mb-4">Maintenance Calendar (Scheduled / In Progress)</h4>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 12%;">Date</th>
                    <th style="width: 14%;">Log No</th>
                    <th>Machine</th>
                    <th style="width: 14%;">Status</th>
                    <th style="width: 14%;">Type</th>
                    <th style="width: 12%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ optional($log->scheduled_date)->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $log->log_number }}</td>
                        <td>
                            {{ $log->machine->name ?? '-' }}
                            <div class="text-muted small">{{ $log->machine->code ?? '' }}</div>
                        </td>
                        <td>{{ ucfirst(str_replace('_',' ', $log->status)) }}</td>
                        <td class="text-capitalize">{{ $log->maintenance_type }}</td>
                        <td>
                            <a href="{{ route('maintenance.logs.show', $log) }}" class="btn btn-sm btn-info">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No scheduled/in-progress logs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('maintenance.logs.index') }}" class="btn btn-secondary">Back to Logs</a>
</div>
@endsection
