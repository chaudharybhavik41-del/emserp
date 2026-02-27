@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-diagram-3"></i> WIP by Activity</h2>
            <div class="text-muted small">Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}</div>
        </div>
        <a href="{{ route('projects.production-dashboard.index', $project) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
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
                    @forelse($rows as $r)
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
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
