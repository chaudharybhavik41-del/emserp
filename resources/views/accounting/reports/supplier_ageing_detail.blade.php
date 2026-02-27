@extends('layouts.erp')

@section('title', 'Supplier Ageing Detail')

@section('content')
@php
    $ledgerFrom = optional($asOfDate)->copy()->startOfMonth()->toDateString();
    $ledgerTo = optional($asOfDate)->toDateString();
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Supplier Ageing - Bills</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('accounting.reports.supplier-ageing', ['supplier_id' => $party->id, 'as_of_date' => optional($asOfDate)->toDateString()]) }}"
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('accounting.reports.ledger', ['account_id' => $account->id, 'from_date' => $ledgerFrom, 'to_date' => $ledgerTo]) }}"
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-journal-text"></i> Ledger
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold small">
                    {{ $party->code }} - {{ $party->name }}
                </div>
                <div class="small text-muted">
                    Ledger: {{ $account->code }} - {{ $account->name }}
                </div>
            </div>
            <div class="small text-muted">
                Ageing as on {{ optional($asOfDate)->toDateString() }}
            </div>
        </div>
    </div>

    <div class="mb-2">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="supplierAgeingDetailSearch" class="form-control" placeholder="Search bill number...">
            <button type="button" class="btn btn-outline-secondary" id="supplierAgeingDetailSearchClear">Clear</button>
        </div>
    </div>

    <div id="supplierAgeingDetailNoMatch" class="alert alert-warning d-none py-2">
        No bills match your search text.
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 14%;">Bill No</th>
                            <th style="width: 12%;">Bill Date</th>
                            <th style="width: 12%;">Due Date</th>
                            <th style="width: 10%;" class="text-end">Bill Amount</th>
                            <th style="width: 10%;" class="text-end">Allocated</th>
                            <th style="width: 10%;" class="text-end">Outstanding</th>
                            <th style="width: 8%;" class="text-end">Days</th>
                            <th style="width: 24%;">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $bill = $row['bill'];
                                $label = $bucketLabels[$row['bucket']] ?? $row['bucket'];
                            @endphp
                            <tr class="supplier-ageing-detail-row" data-search-text="{{ strtolower($row['bill_number'] ?? '') }}">
                                <td class="small">{{ $row['bill_number'] }}</td>
                                <td class="small">{{ optional($row['bill_date'])->toDateString() }}</td>
                                <td class="small">{{ optional($row['due_date'])->toDateString() }}</td>
                                <td class="small text-end">{{ number_format($row['bill_amount'], 2) }}</td>
                                <td class="small text-end">{{ number_format($row['allocated'], 2) }}</td>
                                <td class="small text-end fw-semibold">{{ number_format($row['outstanding'], 2) }}</td>
                                <td class="small text-end">{{ $row['days'] }}</td>
                                <td class="small">{{ $label }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center small text-muted py-2">
                                    No outstanding bills for this supplier / ledger.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows))
                        <tfoot>
                            <tr class="table-light fw-semibold supplier-ageing-detail-total-row">
                                <td colspan="3" class="small text-end">Total</td>
                                <td class="small text-end">{{ number_format($grandTotals['bill_amount'], 2) }}</td>
                                <td class="small text-end">{{ number_format($grandTotals['allocated'], 2) }}</td>
                                <td class="small text-end">{{ number_format($grandTotals['outstanding'], 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('supplierAgeingDetailSearch');
    const clearBtn = document.getElementById('supplierAgeingDetailSearchClear');
    const noMatch = document.getElementById('supplierAgeingDetailNoMatch');
    const rows = Array.from(document.querySelectorAll('.supplier-ageing-detail-row'));
    const totalRow = document.querySelector('.supplier-ageing-detail-total-row');

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
