@extends('layouts.erp')

@section('title', 'Breakdown Register')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-0">Breakdown Register</h4>
            <div class="text-muted small">Reported breakdowns and repair progress</div>
        </div>

        <div>
            @can('machinery.breakdown.create')
                <a href="{{ route('maintenance.breakdowns.create') }}" class="btn btn-primary">+ Report Breakdown</a>
            @endcan
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 14%;">Breakdown #</th>
                    <th style="width: 24%;">Machine</th>
                    <th style="width: 14%;">Type</th>
                    <th style="width: 10%;">Severity</th>
                    <th style="width: 14%;">Reported</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 12%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($breakdowns as $b)
                    @php
                        $sev = $b->severity;
                        $sevClass = match ($sev) {
                            'minor' => 'bg-info',
                            'major' => 'bg-warning',
                            'critical' => 'bg-danger',
                            default => 'bg-secondary',
                        };

                        $st = $b->status;
                        $stClass = match ($st) {
                            'reported' => 'bg-secondary',
                            'acknowledged' => 'bg-info',
                            'in_progress' => 'bg-warning',
                            'resolved' => 'bg-success',
                            'deferred' => 'bg-dark',
                            default => 'bg-primary',
                        };
                    @endphp

                    <tr>
                        <td><strong>{{ $b->breakdown_number }}</strong></td>
                        <td>
                            {{ $b->machine->name ?? '-' }}
                            <div class="text-muted small">{{ $b->machine->code ?? '' }}</div>
                        </td>
                        <td class="text-capitalize">{{ str_replace('_',' ', $b->breakdown_type) }}</td>
                        <td><span class="badge {{ $sevClass }}">{{ ucfirst($sev) }}</span></td>
                        <td>{{ optional($b->reported_at)->format('Y-m-d H:i') ?? '-' }}</td>
                        <td><span class="badge {{ $stClass }}">{{ ucfirst(str_replace('_',' ', $st)) }}</span></td>
                        <td>
                            <a href="{{ route('maintenance.breakdowns.show', $b) }}" class="btn btn-sm btn-info">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No breakdowns found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $breakdowns->links() }}
</div>
@endsection
