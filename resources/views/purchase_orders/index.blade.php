@extends('layouts.erp')

@section('title', 'Purchase Orders')

@section('content')
                        @php
$rows = $orders->getCollection();
$draftCount = $rows->where('status', 'draft')->count();
$approvedCount = $rows->where('status', 'approved')->count();
$cancelledCount = $rows->where('status', 'cancelled')->count();
$pageTotal = (float) $rows->sum(fn($o) => (float) ($o->total_amount ?? 0));
                        @endphp
                        <div class="container-fluid px-0">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h1 class="h4 mb-0"><i class="bi bi-file-earmark-text me-1"></i> Purchase Orders</h1>
                                    <div class="small text-muted">Monitor vendor commitments, approvals, and delivery timelines.</div>
                                </div>

                                <a href="{{ route('purchase-rfqs.index') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-link-45deg me-1"></i> From RFQs
                                </a>
                            </div>

                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <div class="row g-2 mb-3">
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Draft</div><div class="h5 mb-0">{{ $draftCount }}</div></div></div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Approved</div><div class="h5 mb-0">{{ $approvedCount }}</div></div></div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Cancelled</div><div class="h5 mb-0">{{ $cancelledCount }}</div></div></div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border-0 bg-light"><div class="card-body py-2"><div class="small text-muted">Page Total</div><div class="h5 mb-0">{{ number_format($pageTotal, 2) }}</div></div></div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    {{-- <form method="GET" class="row g-2 align-items-end"> --}}
            <form method="GET" id="filterForm" class="row g-3 align-items-end">

                {{-- PO Number --}}
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted">
                        <i class="bi bi-hash me-1"></i> PO Number
                    </label>
                    <input type="text" name="po_number" class="form-control form-control-sm shadow-sm"
                        value="{{ request('po_number') }}" placeholder="Enter PO number">
                </div>

                {{-- Vendor --}}
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted">
                        <i class="bi bi-building me-1"></i> Vendor
                    </label>
                <select name="vendor_id" class="form-select form-select-sm shadow-sm select2">
                    <option value="">All Vendors</option>
                    @foreach(($vendors ?? collect()) as $v)
                        <option value="{{ $v->id }}" @selected((string) request('vendor_id') === (string) $v->id)>
                            {{ $v->name }}
                        </option>
                    @endforeach
                </select>

                </div>

                {{-- Project --}}
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted">
                        <i class="bi bi-kanban me-1"></i> Project
                    </label>
                    <select name="project_id" class="form-select form-select-sm shadow-sm select2">
                        <option value="">All Projects</option>
                        @foreach(($projects ?? collect()) as $p)
                            <option value="{{ $p->id }}" @selected((string) request('project_id') === (string) $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>

                </div>

                {{-- Status --}}
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-semibold text-muted">
                        <i class="bi bi-flag me-1"></i> Status
                    </label>
                    <select name="status" class="form-select form-select-sm shadow-sm">
                        <option value="">All Status</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                    </select>
                </div>

            </form>

                                </div>
                            </div>

                            <div class="card">
                            <div class="card-body p-0" id="tableData">
                                @include('purchase_orders.partials.table')
                            </div>

                            </div>
                        </div>
@endsection
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const form = document.getElementById('filterForm');
        const tableDiv = document.getElementById('tableData');

        function fetchData() {

            const params = new URLSearchParams(new FormData(form)).toString();

            fetch("{{ route('purchase-orders.index') }}?" + params, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.json();
                })
                .then(data => {
                    tableDiv.innerHTML = data.html;
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        }

        // 🔎 PO Number Typing Delay
        let delay;
        const poInput = form.querySelector('input[name="po_number"]');

        if (poInput) {
            poInput.addEventListener('keyup', function () {
                clearTimeout(delay);
                delay = setTimeout(fetchData, 500);
            });
        }

        // 🔽 Select Change
        form.querySelectorAll('select').forEach(function (el) {
            el.addEventListener('change', fetchData);
        });

    });
</script>

<style>
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 5px 10px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 26px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- jQuery (Required) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        $('.select2').select2({
            placeholder: "Select Option",
            allowClear: true,
            width: '100%'
        });
    });
</script>