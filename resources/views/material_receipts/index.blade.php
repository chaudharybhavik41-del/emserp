@extends('layouts.erp')

@section('title', 'Material Receipts (GRN)')

@push('styles')
<style>
    /* Better Select2 UI for Bootstrap 5 */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        height: 31px;
        font-size: 0.875rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        line-height: 29px;
        padding-left: 0.75rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 29px;
    }
    .select2-search__field {
        border-radius: 4px !important;
    }
</style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Material Receipts (GRN)</h1>
        @can('store.material_receipt.create')
            <a href="{{ route('material-receipts.create') }}" class="btn btn-sm btn-primary">
                New GRN
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ route('material-receipts.index') }}" method="GET" class="row g-2" id="grn-filter-form">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" id="grn-search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="GRN / PO / Invoice...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm grn-filter-select select2" data-placeholder="All Suppliers">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Project</label>
                    <select name="project_id" class="form-select form-select-sm grn-filter-select select2" data-placeholder="All Projects">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>{{ $p->code }} - {{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold">Type</label>
                    <select name="type" class="form-select form-select-sm grn-filter-select">
                        <option value="">All</option>
                        <option value="own" @selected(request('type') === 'own')>Own</option>
                        <option value="client" @selected(request('type') === 'client')>Client</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select form-select-sm grn-filter-select">
                        <option value="">All</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="posted" @selected(request('status') === 'posted')>Posted</option>
                        <option value="qc_passed" @selected(request('status') === 'qc_passed')>QC Passed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Accounting</label>
                    <select name="accounting" class="form-select form-select-sm grn-filter-select">
                        <option value="">All</option>
                        <option value="billed" @selected(request('accounting') === 'billed')>Billed</option>
                        <option value="unbilled" @selected(request('accounting') === 'unbilled')>Unbilled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-1">
                    <a href="{{ route('material-receipts.index') }}" class="btn btn-outline-secondary btn-sm px-3 w-100" style="height: 31px; line-height: 18px;">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0" id="grn-list-container">
            @include('material_receipts._list')
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('grn-filter-form');
    const searchInput = document.getElementById('grn-search');
    const selectFilters = document.querySelectorAll('.grn-filter-select');
    const listContainer = document.getElementById('grn-list-container');

    if (!filterForm || !listContainer) return;

    // Initialize Select2 for Supplier and Project
    if (window.$ && $.fn.select2) {
        $('.select2').select2({
            width: '100%',
            theme: 'bootstrap-5',
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: true
        });

        // Focus search box when select2 is opened
        $(document).on('select2:open', function(e) {
            // Use a small delay to ensure the search field is rendered
            setTimeout(function() {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) {
                    searchField.focus();
                }
            }, 50);
        });

        // Open select2 on focus (keyboard tab navigation support)
        $(document).on('focus', '.select2-selection.select2-selection--single', function (e) {
            const $select = $(this).closest(".select2-container").siblings('select:enabled');
            if ($select.length && !$select.data('select2').isOpen()) {
                $select.select2('open');
            }
        });
    }

    function fetchFilteredData() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = `${filterForm.action}?${params.toString()}`;

        // Add loading state
        listContainer.style.opacity = '0.5';
        listContainer.style.pointerEvents = 'none';

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(json => {
                    throw new Error(json.error || 'Server error occurred');
                }).catch(() => {
                    throw new Error('Server error occurred');
                });
            }
            return response.text();
        })
        .then(html => {
            listContainer.innerHTML = html;
            // Restore state
            listContainer.style.opacity = '1';
            listContainer.style.pointerEvents = 'auto';
            
            // Update URL in browser without reload
            window.history.pushState({}, '', url);
        })
        .catch(error => {
            console.error('Error fetching GRN data:', error);
            alert('Error: ' + error.message);
            listContainer.style.opacity = '1';
            listContainer.style.pointerEvents = 'auto';
        });
    }

    // Auto-submit selects on change (handles both native and Select2 change events)
    $(filterForm).on('change', '.grn-filter-select', function() {
        fetchFilteredData();
    });

    // Auto-submit search with debounce (typing delay)
    let searchTimeout;
    searchInput.addEventListener('keyup', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchFilteredData();
        }, 600);
    });

    // Handle paste
    searchInput.addEventListener('input', (e) => {
        if (e.inputType === 'insertFromPaste' || e.inputType === undefined) {
            fetchFilteredData();
        }
    });

    // Prevent full form submission
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchFilteredData();
    });

    // Handle pagination links via AJAX
    listContainer.addEventListener('click', (e) => {
        const link = e.target.closest('.pagination a');
        if (link) {
            e.preventDefault();
            e.stopPropagation();
            const url = link.href;
            
            listContainer.style.opacity = '0.5';
            listContainer.style.pointerEvents = 'none';

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                listContainer.innerHTML = html;
                listContainer.style.opacity = '1';
                listContainer.style.pointerEvents = 'auto';
                window.history.pushState({}, '', url);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    });
});
</script>
@endpush
