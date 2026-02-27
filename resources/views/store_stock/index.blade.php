@extends('layouts.erp')

@section('title', 'Store Stock')
@push('styles')
    <style>
        .select2-container .select2-selection--single {
            height: 31px;
            padding: 2px 6px;
            font-size: 0.875rem;
            border: 1px solid #dee2e6;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
        }

        .select2-dropdown {
            font-size: 0.875rem;
        }
    </style>
@endpush
@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Store Stock</h1>
    </div>

    {{-- ================= FILTER SECTION ================= --}}
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-header bg-white py-2">
            <h6 class="mb-0 fw-semibold text-muted">Filter Stock</h6>
        </div>

        <div class="card-body py-3">
            <form id="stock-filter-form">
                <div class="row g-3 align-items-end">

                    {{-- Item --}}
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label small text-muted">Item</label>
                        <select name="item_id" class="form-select form-select-sm select2">
                            <option value="">All Items</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ (isset($filters['item_id']) && $filters['item_id'] == $item->id) ? 'selected' : '' }}>
                                    {{ $item->code }} - {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Project --}}
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label small text-muted">Project</label>
                        <select name="project_id" class="form-select form-select-sm select2">
                            <option value="">All Projects</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ (isset($filters['project_id']) && $filters['project_id'] == $project->id) ? 'selected' : '' }}>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Category --}}
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">Category</label>
                        <select name="material_category" class="form-select form-select-sm select2">
                            <option value="">All</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ (isset($filters['material_category']) && $filters['material_category'] == $cat) ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $cat)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status --}}
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">Status</label>
                        <select name="status" class="form-select form-select-sm select2">
                            <option value="">All</option>
                            @foreach($statuses as $st)
                                <option value="{{ $st }}" {{ (isset($filters['status']) && $filters['status'] == $st) ? 'selected' : '' }}>
                                    {{ strtoupper($st) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search --}}
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small text-muted">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                            value="{{ $filters['search'] ?? '' }}" placeholder="Item / Plate / Heat">
                    </div>

                    {{-- Only Available --}}
                    <div class="col-lg-2 col-md-6">
                        <div class="form-check mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" value="1" name="only_available"
                                id="only_available" {{ !empty($filters['only_available']) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="only_available">
                                Only Available > 0
                            </label>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>


    {{-- ================= TABLE SECTION ================= --}}
    <div class="card">
        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 16%">Item</th>
                            <th style="width: 8%">Category</th>
                            <th style="width: 12%">Size / Section</th>
                            <th style="width: 8%">Grade</th>
                            <th style="width: 10%">Plate / Heat</th>
                            <th style="width: 12%">Project</th>
                            <th style="width: 8%">Total Pcs</th>
                            <th style="width: 8%">Avail Pcs</th>
                            <th style="width: 8%">Total Wt</th>
                            <th style="width: 8%">Avail Wt</th>
                        </tr>
                    </thead>

                    <tbody id="stock-table-body">
                        @include('store_stock.partials.table', ['stockItems' => $stockItems])
                    </tbody>

                </table>
            </div>
        </div>

        <div class="card-footer pb-0">
            <div id="pagination-links">
                {{ $stockItems->links() }}
            </div>
        </div>
    </div>

@endsection


{{-- ================= AJAX SCRIPT ================= --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Initialize Select2
    $('.select2').select2({
        width: '100%',
        allowClear: true,
        placeholder: "Select option"
    });

    let timeout = null;
    const form = document.getElementById('stock-filter-form');

    function fetchStock(url = null) {

        const formData = new FormData(form);
        const queryString = new URLSearchParams(formData).toString();
        let fetchUrl = url ?? "{{ route('store-stock.index') }}?" + queryString;

        fetch(fetchUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('stock-table-body').innerHTML = html;
        });
    }

    // Select2 change event
    $('.select2').on('change', function () {
        fetchStock();
    });

    // Input typing debounce
    document.querySelectorAll('#stock-filter-form input').forEach(input => {
        input.addEventListener('keyup', function () {
            clearTimeout(timeout);
            timeout = setTimeout(fetchStock, 400);
        });
        input.addEventListener('change', fetchStock);
    });

    // AJAX Pagination
    document.addEventListener('click', function(e){
        if(e.target.closest('.pagination a')){
            e.preventDefault();
            fetchStock(e.target.href);
        }
    });

});
</script>
@endpush