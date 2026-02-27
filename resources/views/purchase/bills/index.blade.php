@extends('layouts.erp')

@section('title', 'Purchase Bills')

@section('content')
        @php
    $rows = $bills->getCollection();
    $draftCount = $rows->where('status', 'draft')->count();
    $postedCount = $rows->where('status', 'posted')->count();
    $cancelledCount = $rows->where('status', 'cancelled')->count();
    $pagePayable = (float) $rows->sum(fn($b) => (float) (($b->total_amount ?? 0) + ($b->tcs_amount ?? 0) - ($b->tds_amount ?? 0)));
        @endphp
        <div class="container-fluid px-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0"><i class="bi bi-receipt-cutoff me-1"></i> Purchase Bills</h1>
                    <div class="small text-muted">Invoice posting, tax impact, and net payable tracking.</div>
                </div>
                <a href="{{ route('purchase.bills.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> New Purchase Bill
                </a>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-3 col-6">
                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Draft</div><div class="h5 mb-0">{{ $draftCount }}</div></div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Posted</div><div class="h5 mb-0">{{ $postedCount }}</div></div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Cancelled</div><div class="h5 mb-0">{{ $cancelledCount }}</div></div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Page Net Payable</div><div class="h5 mb-0">{{ number_format($pagePayable, 2) }}</div></div></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                                <form id="filterForm" class="row g-2 align-items-end">

                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm auto-submit"
                                            placeholder="Bill no / invoice no">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Supplier</label>
                                        <select name="supplier_id" class="form-select form-select-sm auto-submit">
                                            <option value="">-- All --</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>
                                                    {{ $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Project</label>
                                        <select name="project_id" class="form-select form-select-sm auto-submit">
                                            <option value="">-- All --</option>
                                            @foreach($projects as $p)
                                                <option value="{{ $p->id }}" @selected(request('project_id') == $p->id)>
                                                    {{ $p->code }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select form-select-sm auto-submit">
                                            <option value="">-- All --</option>
                                            <option value="draft">Draft</option>
                                            <option value="posted">Posted</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </div>

                                </form>

                </div>
            </div>

            <div class="card">
                <div class="card-body p-0" id="billTable">
                    @include('purchase.bills.partials.table')
                </div>
            </div>
        </div>
@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('filterForm');
            const tableDiv = document.getElementById('billTable');

            function fetchData(url = null) {

                let fetchUrl = url ??
                    "{{ route('purchase.bills.index') }}?" +
                    new URLSearchParams(new FormData(form)).toString();

                fetch(fetchUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.text())
                    .then(data => {
                        tableDiv.innerHTML = data;
                    });
            }

            // Auto filter
            document.querySelectorAll('.auto-submit').forEach(function (el) {

                if (el.tagName === 'SELECT') {
                    el.addEventListener('change', function () {
                        fetchData();
                    });
                }

                if (el.tagName === 'INPUT') {
                    let timer;
                    el.addEventListener('keyup', function () {
                        clearTimeout(timer);
                        timer = setTimeout(fetchData, 500);
                    });
                }

            });

            // AJAX pagination click
            document.addEventListener('click', function (e) {
                if (e.target.closest('.pagination a')) {
                    e.preventDefault();
                    fetchData(e.target.closest('a').href);
                }
            });

        });
    </script>
@endpush