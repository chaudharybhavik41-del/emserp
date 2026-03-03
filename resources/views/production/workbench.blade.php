@extends('layouts.erp')

@section('title', 'Production Workbench')

@php
    $pickRoute = function (array $candidates) {
        foreach ($candidates as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                return $name;
            }
        }
        return null;
    };

    $rProdDash = $pickRoute(['projects.production-dashboard.index']);
    $rProdPlans = $pickRoute(['projects.production-plans.index']);
    $rProdDprs = $pickRoute(['projects.production-dprs.index']);
    $rProdQc = $pickRoute(['projects.production-qc.index']);
    $rProdBill = $pickRoute(['projects.production-billing.index']);
    $rProdDispatch = $pickRoute(['projects.production-dispatches.index']);
    $rProdTrace = $pickRoute(['projects.production-traceability.index']);
@endphp

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production Workbench</h1>
        <div class="small text-body-secondary">Select project and open Production work directly.</div>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('production.workbench') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label mb-1" for="q">Search project</label>
                    <input type="text" class="form-control" id="q" name="q" value="{{ $q ?? '' }}" placeholder="Code or name">
                </div>
                <div class="col-12 col-lg-7">
                    <label class="form-label mb-1" for="project_id">Project</label>
                    <select class="form-select" id="project_id" name="project_id" required data-erp-select data-placeholder="Select project">
                        <option value="">Select Project</option>
                        @foreach($projects as $proj)
                            <option value="{{ $proj->id }}" @selected((int)($selectedProject->id ?? 0) === (int)$proj->id)>
                                {{ $proj->code }} - {{ $proj->name }}{{ !empty($proj->status) ? ' (' . strtoupper($proj->status) . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary">Open Project</button>
                </div>
            </form>
        </div>
    </div>

    @if($selectedProject)
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="fw-semibold">{{ $selectedProject->code }} - {{ $selectedProject->name }}</div>
                <div class="small text-body-secondary">All links below are for this project.</div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="small text-uppercase text-body-secondary mb-1">Approved Plans</div>
                    <div class="h5 mb-0">{{ number_format((int)($stats['plan_approved'] ?? 0)) }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="small text-uppercase text-body-secondary mb-1">Draft DPR</div>
                    <div class="h5 mb-0">{{ number_format((int)($stats['dpr_draft'] ?? 0)) }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="small text-uppercase text-body-secondary mb-1">DPR Today</div>
                    <div class="h5 mb-0">{{ number_format((int)($stats['dpr_today'] ?? 0)) }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="small text-uppercase text-body-secondary mb-1">QC Pending</div>
                    <div class="h5 mb-0">{{ number_format((int)($stats['qc_pending'] ?? 0)) }}</div>
                </div></div>
            </div>
        </div>

        <div class="row g-3">
            @can('production.report.view')
                @if($rProdDash)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdDash, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">Production Dashboard</div>
                                    <div class="small text-body-secondary">Live project production overview</div>
                                </div>
                                <i class="bi bi-speedometer2 text-primary fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan

            @can('production.plan.view')
                @if($rProdPlans)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdPlans, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">Production Plans</div>
                                    <div class="small text-body-secondary">Plan routing and approvals</div>
                                </div>
                                <i class="bi bi-clipboard2-check text-primary fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan

            @can('production.dpr.view')
                @if($rProdDprs)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdDprs, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">Production DPR</div>
                                    <div class="small text-body-secondary">Daily entries from shopfloor</div>
                                </div>
                                <i class="bi bi-journal-check text-primary fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan

            @can('production.qc.perform')
                @if($rProdQc)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdQc, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">QC Pending</div>
                                    <div class="small text-body-secondary">Review and close checks</div>
                                </div>
                                <i class="bi bi-shield-check text-warning fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan

            @can('production.billing.view')
                @if($rProdBill)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdBill, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">Production Billing</div>
                                    <div class="small text-body-secondary">Generate and finalize bills</div>
                                </div>
                                <i class="bi bi-receipt text-success fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan

            @can('production.dispatch.view')
                @if($rProdDispatch)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdDispatch, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">Production Dispatch</div>
                                    <div class="small text-body-secondary">Dispatch planning and status</div>
                                </div>
                                <i class="bi bi-truck text-primary fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan

            @can('production.traceability.view')
                @if($rProdTrace)
                    <div class="col-12 col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none" href="{{ route($rProdTrace, $selectedProject) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark">Traceability Search</div>
                                    <div class="small text-body-secondary">Track plate, piece, and assembly</div>
                                </div>
                                <i class="bi bi-search text-primary fs-4"></i>
                            </div>
                        </a>
                    </div>
                @endif
            @endcan
        </div>
    @else
        <div class="alert alert-info mb-0">
            Select a project to open Production work directly.
        </div>
    @endif
@endsection
