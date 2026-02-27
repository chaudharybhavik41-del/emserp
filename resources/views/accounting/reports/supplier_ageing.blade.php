@extends('layouts.erp')

@section('title', 'Supplier Ageing')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">Supplier Ageing</h4>
        <div class="small text-muted">Company #{{ $companyId }} · As on {{ optional($asOfDate)->toDateString() }}</div>
    </div>

    <form method="GET" class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm">Supplier</label>
            <select class="form-select form-select-sm" name="supplier_id">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected($selectedSupplierId == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm">As on date</label>
            <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ optional($asOfDate)->toDateString() }}">
        </div>
        <div class="col-md-5 d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('accounting.reports.supplier-ageing') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
        <div class="col-md-6">
            <label class="form-label form-label-sm">Quick search supplier</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="supplierAgeingSearch" class="form-control" placeholder="Filter by supplier name...">
                <button type="button" class="btn btn-outline-secondary" id="supplierAgeingSearchClear">Clear</button>
            </div>
        </div>
    </form>

    <div id="supplierAgeingNoMatch" class="alert alert-warning d-none py-2">
        No suppliers match your search text.
    </div>

    <div class="card">
        <div class="card-header"><strong>Summary</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th class="text-end">Not Due</th>
                        <th class="text-end">0-30</th>
                        <th class="text-end">31-60</th>
                        <th class="text-end">61-90</th>
                        <th class="text-end">91-180</th>
                        <th class="text-end">&gt;180</th>
                        <th class="text-end">Total</th>
                        @if($dnEnabled)
                            <th class="text-end">Debit Notes</th>
                            <th class="text-end">Net</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($summaryRows as $r)
                        <tr class="supplier-ageing-row" data-search-text="{{ strtolower($r['party']->name ?? '') }}">
                            <td>
                                <a href="{{ route('accounting.reports.supplier-ageing.bills', [$r['account']->id, 'as_of_date' => optional($asOfDate)->toDateString()]) }}"
                                   class="text-decoration-none">
                                    {{ $r['party']->name }}
                                    <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                </a>
                            </td>
                            <td class="text-end">{{ number_format($r['buckets']['not_due'],2) }}</td>
                            <td class="text-end">{{ number_format($r['buckets']['0_30'],2) }}</td>
                            <td class="text-end">{{ number_format($r['buckets']['31_60'],2) }}</td>
                            <td class="text-end">{{ number_format($r['buckets']['61_90'],2) }}</td>
                            <td class="text-end">{{ number_format($r['buckets']['91_180'],2) }}</td>
                            <td class="text-end">{{ number_format($r['buckets']['over_180'],2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($r['total_outstanding'],2) }}</td>
                            @if($dnEnabled)
                                <td class="text-end">{{ number_format($r['debit_notes'],2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($r['net_outstanding'],2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $dnEnabled ? 10 : 8 }}" class="text-muted">No data</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-semibold supplier-ageing-total-row">
                        <td>Total</td>
                        <td class="text-end">{{ number_format($grand['not_due'],2) }}</td>
                        <td class="text-end">{{ number_format($grand['0_30'],2) }}</td>
                        <td class="text-end">{{ number_format($grand['31_60'],2) }}</td>
                        <td class="text-end">{{ number_format($grand['61_90'],2) }}</td>
                        <td class="text-end">{{ number_format($grand['91_180'],2) }}</td>
                        <td class="text-end">{{ number_format($grand['over_180'],2) }}</td>
                        <td class="text-end">{{ number_format($grand['total'],2) }}</td>
                        @if($dnEnabled)
                            <td class="text-end">{{ number_format($grand['debit_notes'],2) }}</td>
                            <td class="text-end">{{ number_format($grand['net_total'],2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('supplierAgeingSearch');
    const clearBtn = document.getElementById('supplierAgeingSearchClear');
    const noMatch = document.getElementById('supplierAgeingNoMatch');
    const rows = Array.from(document.querySelectorAll('.supplier-ageing-row'));
    const totalRow = document.querySelector('.supplier-ageing-total-row');

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
