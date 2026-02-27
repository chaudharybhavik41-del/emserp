@extends('layouts.erp')

@section('title', 'Material Taxonomy CSV Import / Export')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Material Taxonomy – CSV Import / Export</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(session('import_errors') && is_array(session('import_errors')) && count(session('import_errors')))
    <div class="alert alert-warning">
        <strong>Some rows could not be imported:</strong>
        <ul class="mb-0">
            @foreach(session('import_errors') as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    {{-- Material Types --}}
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-header">
                <strong>Material Types</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Columns:
                    <code>code, name, description, accounting_usage, sort_order, is_active</code>
                </p>

                <div class="mb-3">
                    <a href="{{ route('material-taxonomy.export.types') }}"
                       class="btn btn-outline-primary btn-sm w-100">
                        Download types (CSV)
                    </a>
                </div>

                <form action="{{ route('material-taxonomy.import.types') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Import Types CSV</label>
                        <input type="file"
                               name="file"
                               class="form-control form-control-sm @error('file') is-invalid @enderror"
                               accept=".csv,text/csv">
                        @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        Upload &amp; Import
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Material Categories --}}
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-header">
                <strong>Material Categories</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Columns:
                    <code>type_code, code, name, description, sort_order, is_active</code>
                </p>

                <div class="mb-3">
                    <a href="{{ route('material-taxonomy.export.categories') }}"
                       class="btn btn-outline-primary btn-sm w-100">
                        Download categories (CSV)
                    </a>
                </div>

                <form action="{{ route('material-taxonomy.import.categories') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Import Categories CSV</label>
                        <input type="file"
                               name="file"
                               class="form-control form-control-sm @error('file') is-invalid @enderror"
                               accept=".csv,text/csv">
                        @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        Upload &amp; Import
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Material Subcategories --}}
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-header">
                <strong>Material Subcategories</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Columns:
                    <code>type_code, category_code, code, name, description, sort_order, is_active</code>
                </p>

                <div class="mb-3">
                    <a href="{{ route('material-taxonomy.export.subcategories') }}"
                       class="btn btn-outline-primary btn-sm w-100">
                        Download subcategories (CSV)
                    </a>
                </div>

                <form action="{{ route('material-taxonomy.import.subcategories') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Import Subcategories CSV</label>
                        <input type="file"
                               name="file"
                               class="form-control form-control-sm @error('file') is-invalid @enderror"
                               accept=".csv,text/csv">
                        @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        Upload &amp; Import
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- UNIVERSAL: Type + Category + Subcategory + Item --}}
    <div class="col-md-3">
        <div class="card h-100 border-success">
            <div class="card-header bg-success text-white">
                <strong>Universal Import (Hierarchy + Items)</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Each row creates or updates:
                    <br>
                    <code>type &raquo; category &raquo; subcategory</code> and then <code>item</code>.
                </p>
                <p class="small">
                    Columns:
                    <code>
                        type_code, type_name,
                        category_code, category_name,
                        subcategory_code, subcategory_name, subcategory_item_prefix,
                        item_name, item_short_name, item_grade, item_spec,
                        item_thickness, item_size, item_description,
                        uom_code, hsn_code, gst_rate_percent, item_is_active
                    </code>
                </p>

                <div class="mb-3">
                    <a href="{{ route('material-taxonomy.template.all') }}"
                       class="btn btn-outline-light btn-sm w-100">
                        Download template (CSV)
                    </a>
                </div>

                <form action="{{ route('material-taxonomy.import.all') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Import Hierarchy + Items CSV</label>
                        <input type="file"
                               name="file"
                               class="form-control form-control-sm @error('file') is-invalid @enderror"
                               accept=".csv,text/csv">
                        @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        Upload &amp; Import All
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
