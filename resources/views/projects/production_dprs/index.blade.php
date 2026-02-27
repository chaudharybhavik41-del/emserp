@extends('layouts.erp')

@php
    $pickRoute = function (array $candidates) {
        foreach ($candidates as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) return $name;
        }
        return null;
    };

    // Prefer project-scoped, fallback to global production routes
    $plansIndexRoute = $pickRoute([
        'projects.production-plans.index',
        'production.production-plans.index',
        'production-plans.index',
    ]);

    $dprCreateRoute = $pickRoute([
        'projects.production-dprs.create',
        'production.production-dprs.create',
        'production-dprs.create',
    ]);

    $qcIndexRoute = $pickRoute([
        'projects.production-qc.index',
        'production.production-qc.index',
        'production-qc.index',
    ]);
@endphp

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-journal-check"></i> Production DPR</h2>
            <div class="text-muted small">
                @isset($project)
                    Project: <span class="fw-semibold">{{ $project->code }}</span> — {{ $project->name }}
                @else
                    Project: <span class="text-muted">—</span>
                @endisset
            </div>
        </div>

        <div class="d-flex gap-2">
            @if($plansIndexRoute)
                <a href="{{ route($plansIndexRoute, isset($project) ? $project : []) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-clipboard2-check"></i> Production Plans
                </a>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET"
                  action="{{ $dprCreateRoute ? route($dprCreateRoute, isset($project) ? $project : []) : '#' }}"
                  class="row g-2 align-items-end">

                <div class="col-md-4">
                    <label class="form-label">Approved Plan</label>
                    <select name="plan_id" class="form-select" required>
                        <option value="">— select —</option>
                        @foreach(($plans ?? []) as $p)
                            <option value="{{ $p->id }}">{{ $p->plan_number }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Activity</label>
                    <select name="activity_id" class="form-select" required>
                        <option value="">— select —</option>
                        @foreach(($activities ?? []) as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="dpr_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Shift</label>
                    <input type="text" name="shift" class="form-control" placeholder="A/B/N">
                </div>

                <div class="col-12 mt-2">
                    <button class="btn btn-primary" {{ $dprCreateRoute ? '' : 'disabled' }}>
                        <i class="bi bi-plus-circle"></i> Create DPR
                    </button>

                    @if($qcIndexRoute)
                        <a href="{{ route($qcIndexRoute, isset($project) ? $project : []) }}" class="btn btn-outline-warning ms-2">
                            <i class="bi bi-shield-check"></i> QC Pending
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Activity</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($dprs ?? []) as $d)
                        <tr>
                            <td class="fw-semibold">#{{ $d->id }}</td>
                            <td>{{ optional($d->dpr_date)->format('Y-m-d') }}</td>
                            <td>{{ $d->plan?->plan_number }}</td>
                            <td>{{ $d->activity?->name }}</td>
                            <td>
                                @if($d->status === 'approved')
                                    <span class="badge text-bg-success">Approved</span>
                                @elseif($d->status === 'submitted')
                                    <span class="badge text-bg-primary">Submitted</span>
                                @else
                                    <span class="badge text-bg-secondary">Draft</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @php
                                    $dprShowRoute = $pickRoute([
                                        'projects.production-dprs.show',
                                        'production.production-dprs.show',
                                        'production-dprs.show',
                                    ]);
                                @endphp
                                @if($dprShowRoute)
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route($dprShowRoute, isset($project) ? [$project, $d] : [$d]) }}">
                                        Open
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No DPR created yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if(isset($dprs) && method_exists($dprs, 'links'))
                <div class="mt-3">
                    {{ $dprs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
