@extends('layouts.erp')

@section('title', 'Purchase Indents')

@section('content')
    <div class="container-fluid">

       
            <div class="d-flex flex-column flex-md-row 
                        justify-content-between align-items-md-center mb-3">

                <div>
                    <h4 class="mb-1 fw-semibold">
                        Purchase Indents
                    </h4>

                    <small class="text-muted">
                        Track request, approval, and procurement progress in one place.
                    </small>
                </div>

                <a href="{{ route('purchase-indents.create') }}" class="btn btn-primary mt-2 mt-md-0">
                    <i class="bi bi-plus-circle me-1"></i> New Indent
                </a>

            </div>

        {{-- <h4 class="mb-3">Purchase Indents</h4> --}}

        {{-- SUMMARY CARDS --}}
        <div id="summaryCards">
            @include('purchase_indents.partials.summary', ['indents' => $indents])
        </div>

        {{-- FILTER FORM --}}
        <div class="card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-2">

                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                            placeholder="Indent No">
                    </div>

                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach($statusOptions as $k => $v)
                                <option value="{{ $k }}" @selected(request('status') == $k)>
                                    {{ $v }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Procurement</label>
                        <select name="procurement_status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach($procurementOptions as $k => $v)
                                <option value="{{ $k }}" @selected(request('procurement_status') == $k)>
                                    {{ $v }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Project</label>
                        <select name="project_id" class="form-select form-select-sm ">
                            <option value="">All</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>
                                    {{ $p->code }} 
                                     {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </form>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="card">
            <div class="card-body p-0">
                <div id="indentTable">
                    @include('purchase_indents.partials.table', ['indents' => $indents])
                </div>
            </div>
        </div>

    </div>
@endsection