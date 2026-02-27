@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-clipboard2-check"></i> Production Plans</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        @can('production.plan.create')
            <a href="{{ route('projects.production-plans.create', $project) }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Plan (from Approved BOM)
            </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Plan No</th>
                        <th>BOM</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Approved</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $p)
                        <tr>
                            <td class="fw-semibold">{{ $p->plan_number }}</td>
                            <td>{{ $p->bom?->bom_number ?? '—' }}</td>
                            <td>{{ $p->plan_date?->format('Y-m-d') }}</td>
                            <td>
                                @if($p->status === 'approved')
                                    <span class="badge text-bg-success">Approved</span>
                                @elseif($p->status === 'cancelled')
                                    <span class="badge text-bg-dark">Cancelled</span>
                                @else
                                    <span class="badge text-bg-secondary">Draft</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                @if($p->approved_at)
                                    {{ $p->approved_at->format('Y-m-d H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-plans.show', [$project, $p]) }}" class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No production plans created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $plans->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
