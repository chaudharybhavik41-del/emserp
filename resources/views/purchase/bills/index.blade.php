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
                <div class="d-flex gap-2">
                    <a
                        href="{{ route('purchase.bills.tally.export', request()->query()) }}"
                        class="btn btn-outline-success btn-sm"
                        id="tally-export-link"
                    >
                        <i class="bi bi-download me-1"></i> Tally XML
                    </a>
                    <a href="{{ route('purchase.bills.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> New Purchase Bill
                    </a>
                </div>
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
                                                    {{ $supplier->legal_name }}
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

        <!-- GRN Modal -->
        <div class="modal fade" id="grnModal" tabindex="-1" aria-labelledby="grnModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom-0 ps-3">
                        <h5 class="modal-title" id="grnModalLabel">
                            <i class="bi bi-box-seam me-1"></i> Associated GRNs - <span id="modalBillNo" class="text-primary fw-bold"></span>
                        </h5>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="grnLoading" class="p-5 text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2 text-muted small">Fetching associated GRNs...</div>
                        </div>
                        <div class="table-responsive" id="grnTableContainer" style="display: none;">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 25%">GRN Info</th>
                                        <th style="width: 45%">Item Details</th>
                                        <th class="text-end" style="width: 15%">Qty</th>
                                        <th class="text-end pe-3" style="width: 15%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="grnTableBody"></tbody>
                            </table>
                        </div>
                        <div id="grnEmpty" class="p-5 text-center text-muted" style="display: none;">
                            <i class="bi bi-info-circle mb-2 h3 d-block"></i>
                            No associated GRNs found for this bill.
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0 pb-3 hstack gap-2 justify-content-end pe-3">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('filterForm');
            const tableDiv = document.getElementById('billTable');
            const tallyExportLink = document.getElementById('tally-export-link');

            function updateTallyExportLink() {
                if (!tallyExportLink) {
                    return;
                }

                const params = new URLSearchParams(new FormData(form));
                tallyExportLink.href = "{{ route('purchase.bills.tally.export') }}?" + params.toString();
            }

            function fetchData(url = null) {
                updateTallyExportLink();

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

            updateTallyExportLink();

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

            // GRN Modal logic
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-grn-modal');
                if (btn) {
                    const billId = btn.getAttribute('data-bill-id');
                    const modalEl = document.getElementById('grnModal');
                    const bootstrapModal = bootstrap.Modal.getOrCreateInstance(modalEl);

                    const targetBody = document.getElementById('grnTableBody');
                    document.getElementById('grnLoading').style.display = 'block';
                    document.getElementById('grnTableContainer').style.display = 'none';
                    document.getElementById('grnEmpty').style.display = 'none';
                    targetBody.innerHTML = '';
                    document.getElementById('modalBillNo').innerText = '...';

                    bootstrapModal.show();

                    fetch(`/purchase/bills/${billId}/grns`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('grnLoading').style.display = 'none';
                        document.getElementById('modalBillNo').innerText = data.bill_number ?? '...';

                        if (data.lines && data.lines.length > 0) {
                            document.getElementById('grnTableContainer').style.display = 'block';
                            let html = '';
                            data.lines.forEach(line => {
                                const receiptDate = line.receipt_date ? new Date(line.receipt_date).toLocaleDateString('en-GB').replace(/\//g, '-') : '-';
                                html += `
                                    <tr>
                                        <td class="ps-3 small">
                                            <div class="fw-semibold text-primary font-monospace">${line.receipt_number}</div>
                                            <div class="text-muted" style="font-size: 10px;">${receiptDate} | PO: ${line.po_code ?? '-'}</div>
                                        </td>
                                        <td class="small">
                                            <div class="fw-bold text-dark">${line.item_name}</div>
                                            <div class="text-muted" style="font-size: 10px;">${line.item_code} | UOM: ${line.uom}</div>
                                        </td>
                                        <td class="text-end small fw-semibold">${line.qty}</td>
                                        <td class="text-end pe-3">
                                            <a href="/material-receipts/${line.grn_id}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;" target="_blank">View GRN</a>
                                        </td>
                                    </tr>
                                `;
                            });
                            targetBody.innerHTML = html;
                        } else {
                            document.getElementById('grnEmpty').style.display = 'block';
                        }
                    })
                    .catch(err => {
                         document.getElementById('grnLoading').style.display = 'none';
                         console.error(err);
                         alert('Failed to load GRNs');
                    });
                }
            });
        });
    </script>
@endpush
