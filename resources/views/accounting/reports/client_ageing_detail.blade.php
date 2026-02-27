@extends('layouts.erp')

@section('title', 'Client Ageing - Bills')

@section('content')
@php
    $ledgerFrom = optional($asOfDate)->copy()->startOfMonth()->toDateString();
    $ledgerTo = optional($asOfDate)->toDateString();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">Client Ageing - Bill Wise</h1>
            <div class="small text-muted">
                {{ $party->code }} - {{ $party->name }} &nbsp;|&nbsp;
                Ledger: {{ $account->code }} - {{ $account->name }}
            </div>
        </div>
        <div class="small text-muted">
            As on: {{ optional($asOfDate)->toDateString() }}
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body small">
            <div class="d-flex flex-wrap gap-3">
                <div>
                    <span class="text-muted">Bill Outstanding:</span>
                    <span class="fw-semibold">{{ number_format($grandTotals['outstanding'] ?? 0, 2) }}</span>
                </div>
                <div>
                    <span class="text-muted">On-Account:</span>
                    <span class="fw-semibold">{{ number_format($onAccount ?? 0, 2) }}</span>
                </div>
                <div>
                    <span class="text-muted">Net Outstanding:</span>
                    <span class="fw-semibold">{{ number_format($netOutstanding ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">Open Bills</div>
            <div class="small">
                <a href="{{ route('accounting.reports.client-ageing', ['client_id' => $party->id, 'as_of_date' => optional($asOfDate)->toDateString(), 'status' => $status]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a href="{{ route('accounting.reports.ledger', ['account_id' => $account->id, 'from_date' => $ledgerFrom, 'to_date' => $ledgerTo]) }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-text"></i> Ledger
                </a>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="p-2 border-bottom">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="clientAgeingDetailSearch" class="form-control" placeholder="Search bill number...">
                    <button type="button" class="btn btn-outline-secondary" id="clientAgeingDetailSearchClear">Clear</button>
                </div>
                <div id="clientAgeingDetailNoMatch" class="alert alert-warning d-none py-2 mb-0 mt-2">
                    No bills match your search text.
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 14%;">Bill No</th>
                            <th style="width: 10%;">Bill Date</th>
                            <th style="width: 10%;">Due Date</th>
                            <th style="width: 10%;" class="text-end">Bill Amount</th>
                            <th style="width: 10%;" class="text-end">Allocated</th>
                            <th style="width: 10%;" class="text-end">Outstanding</th>
                            <th style="width: 10%;" class="text-end">Age (days)</th>
                            <th style="width: 16%;">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($rows) && count($rows))
                            @foreach($rows as $r)
                                <tr class="client-ageing-detail-row" data-search-text="{{ strtolower($r['bill_number'] ?? '') }}">
                                    <td class="small">{{ $r['bill_number'] }}</td>
                                    <td class="small">{{ optional($r['bill_date'])->toDateString() }}</td>
                                    <td class="small">{{ optional($r['due_date'])->toDateString() }}</td>
                                    <td class="small text-end">{{ number_format($r['bill_amount'], 2) }}</td>
                                    <td class="small text-end">{{ number_format($r['allocated'], 2) }}</td>
                                    <td class="small text-end fw-semibold">{{ number_format($r['outstanding'], 2) }}</td>
                                    <td class="small text-end">{{ (int)($r['days'] ?? 0) }}</td>
                                    <td class="small">
                                        <span class="badge bg-light text-dark border">{{ $bucketLabels[$r['bucket']] ?? $r['bucket'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="8" class="text-center small text-muted py-2">No open bills for this client.</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-semibold client-ageing-detail-total-row">
                            <td colspan="3" class="text-end small">Total</td>
                            <td class="small text-end">{{ number_format($grandTotals['bill_amount'] ?? 0, 2) }}</td>
                            <td class="small text-end">{{ number_format($grandTotals['allocated'] ?? 0, 2) }}</td>
                            <td class="small text-end">{{ number_format($grandTotals['outstanding'] ?? 0, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('clientAgeingDetailSearch');
    const clearBtn = document.getElementById('clientAgeingDetailSearchClear');
    const noMatch = document.getElementById('clientAgeingDetailNoMatch');
    const rows = Array.from(document.querySelectorAll('.client-ageing-detail-row'));
    const totalRow = document.querySelector('.client-ageing-detail-total-row');

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
