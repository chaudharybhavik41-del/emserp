@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-speedometer2"></i> Production Dashboard</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('projects.production-dprs.index', $project) }}" class="btn btn-outline-primary">
                <i class="bi bi-journal-check"></i> DPR
            </a>
            <a href="{{ route('projects.production-plans.index', $project) }}" class="btn btn-outline-secondary">
                <i class="bi bi-clipboard2-check"></i> Plans
            </a>
            <a href="{{ route('projects.production-qc.index', $project) }}" class="btn btn-outline-warning">
                <i class="bi bi-shield-check"></i> QC Pending
                @if($qcPendingCount > 0)
                    <span class="badge text-bg-danger ms-1">{{ $qcPendingCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Plans</div>
                    <div class="fs-4 fw-semibold">{{ (int)$planCounts->approved_count }}</div>
                    <div class="small text-muted">
                        Draft: {{ (int)$planCounts->draft_count }} |
                        Cancelled: {{ (int)$planCounts->cancelled_count }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">WIP Items</div>
                    <div class="fs-4 fw-semibold">{{ (int)$itemCounts->in_progress_count }}</div>
                    <div class="small text-muted">
                        Pending: {{ (int)$itemCounts->pending_count }} |
                        Done: {{ (int)$itemCounts->done_count }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">DPR</div>
                    <div class="fs-4 fw-semibold">{{ (int)$dprCounts->approved_count }}</div>
                    <div class="small text-muted">
                        Draft: {{ (int)$dprCounts->draft_count }} |
                        Submitted: {{ (int)$dprCounts->submitted_count }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Billing (Phase E1)</div>
                    <div class="fs-4 fw-semibold">₹ {{ number_format((float)$billSummary->bill_total, 2) }}</div>
                    <div class="small text-muted">
                        Bills: {{ (int)$billSummary->bill_count }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Pieces</div>
                    <div class="fs-5 fw-semibold">
                        Available: {{ (int)$pieceCounts->available_count }}
                    </div>
                    <div class="small text-muted">
                        Consumed: {{ (int)$pieceCounts->consumed_count }} |
                        Scrap: {{ (int)$pieceCounts->scrap_count }}
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('projects.production-dashboard.remnants', $project) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-seam"></i> Remnants
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Assemblies</div>
                    <div class="fs-5 fw-semibold">
                        In Progress: {{ (int)$assemblyCounts->in_progress_count }}
                    </div>
                    <div class="small text-muted">
                        Completed: {{ (int)$assemblyCounts->completed_count }}
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('projects.production-dashboard.wip-activity', $project) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-diagram-3"></i> WIP by Activity
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Remnants (Project)</div>
                    <div class="fs-5 fw-semibold">
                        Usable Available: {{ (int)$remnantCounts->usable_available_count }}
                    </div>
                    <div class="small text-muted">
                        Available: {{ (int)$remnantCounts->available_count }} |
                        Weight: {{ number_format((float)$remnantCounts->available_weight_kg, 3) }} kg
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('projects.production-billing.index', $project) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-receipt"></i> Billing
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-diagram-3"></i> WIP by Activity (Approved Plans)</span>
            <a href="{{ route('projects.production-dashboard.wip-activity', $project) }}" class="btn btn-sm btn-outline-primary">
                View detail
            </a>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th class="text-end">Pending</th>
                        <th class="text-end">In Progress</th>
                        <th class="text-end">Done</th>
                        <th class="text-end">QC Pending</th>
                        <th class="text-end">QC Failed</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activityWip as $r)
                        <tr>
                            <td class="fw-semibold">{{ $r->name }}</td>
                            <td class="text-end">{{ (int)$r->pending }}</td>
                            <td class="text-end">{{ (int)$r->in_progress }}</td>
                            <td class="text-end">{{ (int)$r->done }}</td>
                            <td class="text-end">
                                @if((int)$r->qc_pending > 0)
                                    <span class="badge text-bg-warning">{{ (int)$r->qc_pending }}</span>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-end">
                                @if((int)$r->qc_failed > 0)
                                    <span class="badge text-bg-danger">{{ (int)$r->qc_failed }}</span>
                                @else
                                    0
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if($activityWip->isEmpty())
                        <tr><td colspan="6" class="text-center text-muted py-3">No approved plan activity routes found.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history"></i> Recent DPR</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Activity</th>
                        <th>Status</th>
                        <th class="text-end">Open</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentDprs as $d)
                        <tr>
                            <td class="fw-semibold">#{{ $d->id }}</td>
                            <td>{{ $d->dpr_date?->format('Y-m-d') }}</td>
                            <td>{{ $d->plan?->plan_number }}</td>
                            <td>{{ $d->activity?->name }}</td>
                            <td>
                                <span class="badge {{ $d->status === 'approved' ? 'text-bg-success' : ($d->status === 'submitted' ? 'text-bg-primary' : 'text-bg-secondary') }}">
                                    {{ ucfirst($d->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('projects.production-dprs.show', [$project, $d]) }}">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No DPR yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
