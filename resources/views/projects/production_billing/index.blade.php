@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-receipt"></i> Production Billing</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-billing.create', $project) }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Generate Bill
        </a>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Contractor</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $b)
                        <tr>
                            <td class="fw-semibold">{{ $b->bill_number }}</td>
                            <td>{{ $b->contractor?->name }}</td>
                            <td>{{ $b->period_from?->format('Y-m-d') }} → {{ $b->period_to?->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge {{ $b->status === 'draft' ? 'text-bg-secondary' : 'text-bg-success' }}">
                                    {{ ucfirst($b->status) }}
                                </span>
                            </td>
                            <td class="text-end">₹ {{ number_format((float)$b->grand_total, 2) }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('projects.production-billing.show', [$project, $b]) }}">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No bills generated yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-3">{{ $bills->links() }}</div>
        </div>
    </div>
</div>
@endsection
