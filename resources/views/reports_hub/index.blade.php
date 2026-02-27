@extends('layouts.erp')

@section('title', 'Reports Hub')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-bar-chart-line me-2"></i> Reports Hub
            </h1>
            <div class="text-muted small">All module reports in one place (filters + PDF/Print/CSV).</div>
        </div>

        <form method="GET" action="{{ route('reports-hub.index') }}" class="d-flex gap-2">
            <input type="text"
                   name="q"
                   value="{{ $q ?? '' }}"
                   class="form-control form-control-sm"
                   placeholder="Search reports...">
            <button class="btn btn-sm btn-primary">
                <i class="bi bi-search"></i>
            </button>
            @if(!empty($q))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports-hub.index') }}">
                    Reset
                </a>
            @endif
        </form>
    </div>

    @if(empty($grouped))
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No reports found.
        </div>
    @else
        <div class="row g-3">
            @foreach($grouped as $module => $reports)
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="fw-semibold">
                                    <i class="bi bi-grid-1x2 me-2 text-muted"></i>{{ $module }}
                                </div>
                                <span class="badge text-bg-light">{{ count($reports) }}</span>
                            </div>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach($reports as $r)
                                <a href="{{ route('reports-hub.show', $r->key()) }}"
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                                    <div class="me-3">
                                        <div class="fw-semibold">{{ $r->name() }}</div>
                                        @if($r->description())
                                            <div class="text-muted small">{{ $r->description() }}</div>
                                        @endif
                                    </div>
                                    <div class="text-muted">
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
