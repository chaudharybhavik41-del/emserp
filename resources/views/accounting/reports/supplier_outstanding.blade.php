@extends('layouts.erp')

@section('title', 'Supplier Outstanding')

@section('content')
@php
    $ledgerFrom = optional($asOfDate)->copy()->startOfMonth()->toDateString();
    $ledgerTo = optional($asOfDate)->toDateString();
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">Supplier Outstanding</h4>
        <div class="small text-muted">Company #{{ $companyId }} · As on {{ $ledgerTo }}</div>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Supplier</label>
            <select class="form-select form-select-sm" name="supplier_id">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected($selectedParty && $selectedParty->id == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm">As on date</label>
            <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ $ledgerTo }}">
        </div>
        <div class="col-md-5 d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('accounting.reports.supplier-outstanding') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
        <div class="col-md-6">
            <label class="form-label form-label-sm">Quick search supplier</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="supplierOutstandingSearch" class="form-control" placeholder="Filter by supplier name...">
                <button type="button" class="btn btn-outline-secondary" id="supplierOutstandingSearchClear">Clear</button>
            </div>
        </div>
    </form>

    <div id="supplierOutstandingNoMatch" class="alert alert-warning d-none py-2">
        No suppliers match your search text.
    </div>

    <div class="card">
        <div class="card-header"><strong>Summary</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th class="text-end">Bill Amount</th>
                        <th class="text-end">Allocated</th>
                        <th class="text-end">Bill Outstanding</th>
                        @if($dnEnabled)
                            <th class="text-end">Debit Notes</th>
                            <th class="text-end">Net Outstanding</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryRows as $r)
                        <tr class="supplier-outstanding-row" data-search-text="{{ strtolower($r['party']->name ?? '') }}">
                            <td>
                                <a href="{{ route('accounting.reports.supplier-outstanding', ['supplier_id' => $r['party']->id, 'as_of_date' => $ledgerTo]) }}"
                                   class="text-decoration-none">
                                    {{ $r['party']->name }}
                                    <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                </a>
                            </td>
                            <td class="text-end">{{ number_format($r['bill_amount'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['allocated'], 2) }}</td>
                            <td class="text-end">{{ number_format($r['outstanding'], 2) }}</td>
                            @if($dnEnabled)
                                <td class="text-end">{{ number_format($r['debit_notes'], 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($r['net_outstanding'], 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $dnEnabled ? 6 : 4 }}" class="text-muted">No data</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-semibold supplier-outstanding-total-row">
                        <td>Total</td>
                        <td class="text-end">{{ number_format($grandTotalBill, 2) }}</td>
                        <td class="text-end">{{ number_format($grandTotalAlloc, 2) }}</td>
                        <td class="text-end">{{ number_format($grandTotalOutstd, 2) }}</td>
                        @if($dnEnabled)
                            <td class="text-end">{{ number_format($grandTotalDn, 2) }}</td>
                            <td class="text-end">{{ number_format($grandTotalNet, 2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if($selectedParty)
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Detail: {{ $selectedParty->name }}</strong>
                <div class="d-flex gap-2">
                    @if($selectedAccount)
                        <a href="{{ route('accounting.reports.ledger', [
                            'account_id' => $selectedAccount->id,
                            'from_date' => $ledgerFrom,
                            'to_date' => $ledgerTo,
                        ]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-journal-text"></i> Ledger
                        </a>
                    @endif
                    <a href="{{ route('accounting.reports.supplier-ageing', ['supplier_id' => $selectedParty->id, 'as_of_date' => $ledgerTo]) }}"
                       class="btn btn-sm btn-outline-secondary">
                        Ageing
                    </a>
                </div>
            </div>
            <div class="card-body">
                <h6>Open Bills</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Bill No</th><th>Date</th><th class="text-end">Outstanding</th></tr></thead>
                        <tbody>
                        @forelse($detailBills as $row)
                            @php $b = $row['bill']; @endphp
                            <tr>
                                <td>{{ $b->bill_number }}</td>
                                <td>{{ optional($b->bill_date)->toDateString() }}</td>
                                <td class="text-end">{{ number_format((float)$row['outstanding'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No open bills</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($dnEnabled)
                    <h6 class="mt-3">Purchase Debit Notes</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Note No</th><th>Date</th><th>Voucher</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                            @forelse($detailDebitNotes as $dn)
                                <tr>
                                    <td>{{ $dn->note_number }}</td>
                                    <td>{{ optional($dn->note_date)->toDateString() }}</td>
                                    <td>{{ $dn->voucher?->voucher_no }}</td>
                                    <td class="text-end">{{ number_format((float)$dn->total_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">No debit notes</td></tr>
                            @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td colspan="3">Debit Notes Total</td>
                                    <td class="text-end">{{ number_format((float)$detailDnTotal, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('supplierOutstandingSearch');
    const clearBtn = document.getElementById('supplierOutstandingSearchClear');
    const noMatch = document.getElementById('supplierOutstandingNoMatch');
    const rows = Array.from(document.querySelectorAll('.supplier-outstanding-row'));
    const totalRow = document.querySelector('.supplier-outstanding-total-row');

    function applySearch() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const txt = row.dataset.searchText || '';
            const match = !query || txt.includes(query);
            row.classList.toggle('d-none', !match);
            if (match) visible += 1;
        });

        if (totalRow) totalRow.classList.toggle('d-none', !!query);
        if (noMatch) noMatch.classList.toggle('d-none', !(query && visible === 0));
    }

    if (searchInput) searchInput.addEventListener('input', applySearch);
    if (clearBtn && searchInput) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applySearch();
            searchInput.focus();
        });
    }
});
</script>
@endpush
