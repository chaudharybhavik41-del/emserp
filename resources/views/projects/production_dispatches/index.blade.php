@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-truck"></i> Production Dispatch</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-dispatches.create', $project) }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Dispatch
        </a>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Dispatch No</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th class="text-end">Total Qty</th>
                        <th class="text-end">Total Weight (kg)</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dispatches as $d)
                        <tr>
                            <td class="fw-semibold">{{ $d->dispatch_number }}</td>
                            <td>{{ $d->dispatch_date?->format('Y-m-d') }}</td>
                            <td>{{ $d->client?->name ?? '-' }}</td>
                            <td>{{ $d->plan?->plan_number ?? '-' }}</td>
                            <td>
                                @php
                                    $badge = match($d->status) {
                                        'finalized' => 'text-bg-success',
                                        'cancelled' => 'text-bg-danger',
                                        default => 'text-bg-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($d->status) }}</span>
                            </td>
                            <td class="text-end">{{ number_format((float)$d->total_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float)$d->total_weight_kg, 3) }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('projects.production-dispatches.show', [$project, $d]) }}">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No dispatches created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-3">{{ $dispatches->links() }}</div>
        </div>
    </div>
</div>
@endsection
