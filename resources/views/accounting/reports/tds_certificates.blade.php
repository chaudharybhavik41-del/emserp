@extends('layouts.erp')

@section('title', 'TDS Certificates')

@section('content')
@php
    $doneLabel = ($direction === 'payable') ? 'Issued' : 'Received';
    $ledgerFrom = $fromDate ?: now()->startOfMonth()->toDateString();
    $ledgerTo = $toDate ?: now()->toDateString();
    $pagePending = $rows->getCollection()->filter(fn($r) => empty($r->certificate_no))->count();
    $pageDone = $rows->getCollection()->filter(fn($r) => !empty($r->certificate_no))->count();
@endphp

<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h4 class="mb-1">TDS Certificates</h4>
            <div class="small text-muted">{{ ucfirst($direction) }} · Total TDS (filtered): <strong>{{ number_format((float) $totalTds, 2) }}</strong></div>
        </div>

        @if($direction === 'payable')
            <form method="POST" action="{{ route('accounting.reports.tds-certificates.sync-payable') }}"
                  onsubmit="return confirm('Sync payable-side certificate rows for already posted Purchase Bills and Subcontractor RA Bills?');">
                @csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-arrow-repeat"></i> Sync Payable Bills
                </button>
            </form>
        @endif
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Current page rows</div>
            <div class="h6 mb-0">{{ $rows->count() }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Pending</div>
            <div class="h6 mb-0 text-warning">{{ $pagePending }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">{{ $doneLabel }}</div>
            <div class="h6 mb-0 text-success">{{ $pageDone }}</div>
        </div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.tds-certificates') }}" class="row g-3 align-items-end">
                <input type="hidden" name="company_id" value="{{ $companyId }}">

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Direction</label>
                    <select name="direction" class="form-select form-select-sm">
                        <option value="receivable" {{ $direction === 'receivable' ? 'selected' : '' }}>Receivable (Client)</option>
                        <option value="payable" {{ $direction === 'payable' ? 'selected' : '' }}>Payable (Supplier/Subcontractor)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="received" {{ $status === 'received' ? 'selected' : '' }}>{{ $doneLabel }}</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">From Date</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">To Date</label>
                    <input type="date" name="to_date" value="{{ $toDate }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <label class="form-label form-label-sm">Party</label>
                    <select name="party_account_id" class="form-select form-select-sm">
                        <option value="">-- All Parties --</option>
                        @foreach($parties as $p)
                            <option value="{{ $p->id }}" {{ (string) $partyId === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }} ({{ $p->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('accounting.reports.tds-certificates') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="tdsSearch" class="form-control" placeholder="Voucher / party / section / cert no / status...">
                        <button type="button" id="tdsSearchClear" class="btn btn-outline-secondary">Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher</th>
                            <th>Party</th>
                            <th>Section</th>
                            <th class="text-end">Rate %</th>
                            <th class="text-end">TDS Amount</th>
                            <th>Certificate No</th>
                            <th>Certificate Date</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="tdsNoMatch" class="d-none">
                            <td colspan="10" class="text-center text-muted py-4">No rows match the search text.</td>
                        </tr>
                        @forelse($rows as $row)
                            @php
                                $partyName = optional($row->partyAccount)->name ?? '-';
                                $voucherNo = optional($row->voucher)->voucher_no ?? '';
                                $isDone = !empty($row->certificate_no);
                                $searchText = strtolower(trim(
                                    (optional($row->voucher)->voucher_date?->format('Y-m-d') ?? '') . ' ' .
                                    $voucherNo . ' ' .
                                    $partyName . ' ' .
                                    ($row->tds_section ?? '') . ' ' .
                                    ($row->certificate_no ?? '') . ' ' .
                                    ($isDone ? $doneLabel : 'pending')
                                ));
                            @endphp
                            <tr class="tds-row" data-row-text="{{ $searchText }}">
                                <td>{{ optional($row->voucher)->voucher_date ? optional($row->voucher)->voucher_date->format('d-m-Y') : '-' }}</td>
                                <td>
                                    @if($row->voucher)
                                        <a href="{{ route('accounting.vouchers.show', $row->voucher->id) }}" class="text-decoration-none">{{ $row->voucher->voucher_no }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($row->partyAccount)
                                        <a href="{{ route('accounting.reports.ledger', ['account_id' => $row->partyAccount->id, 'from_date' => $ledgerFrom, 'to_date' => $ledgerTo]) }}" class="text-decoration-none">
                                            {{ $partyName }}
                                        </a>
                                    @else
                                        {{ $partyName }}
                                    @endif
                                </td>
                                <td>{{ $row->tds_section ?? '-' }}</td>
                                <td class="text-end">{{ $row->tds_rate ? number_format((float) $row->tds_rate, 4) : '-' }}</td>
                                <td class="text-end">{{ number_format((float) $row->tds_amount, 2) }}</td>
                                <td>{{ $row->certificate_no ?: '-' }}</td>
                                <td>{{ $row->certificate_date ? $row->certificate_date->format('d-m-Y') : '-' }}</td>
                                <td>
                                    @if($isDone)
                                        <span class="badge bg-success">{{ $doneLabel }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.reports.tds-certificates.edit', $row->id) }}">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No records found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.tds-row:hover td { background: #f7faff; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('tdsSearch');
    const clearBtn = document.getElementById('tdsSearchClear');
    const rows = Array.from(document.querySelectorAll('.tds-row'));
    const noMatch = document.getElementById('tdsNoMatch');
    if (!input || !rows.length) return;

    const applyFilter = function () {
        const needle = (input.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const hay = (row.dataset.rowText || row.textContent || '').toLowerCase();
            const show = needle === '' || hay.includes(needle);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (noMatch) noMatch.classList.toggle('d-none', needle === '' || visible > 0);
    };

    input.addEventListener('input', applyFilter);
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter();
        });
    }

    applyFilter();
});
</script>
@endpush
