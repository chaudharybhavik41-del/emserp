@extends('layouts.erp')

@section('title', 'Cash Flow Statement')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Cash Flow Statement (Direct)</h1>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date"
                           name="from_date"
                           value="{{ request('from_date', optional($fromDate)->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date"
                           name="to_date"
                           value="{{ request('to_date', optional($toDate)->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm mt-3 mt-md-0">
                        <i class="bi bi-filter"></i> View
                    </button>
                    <a href="{{ route('accounting.reports.cash-flow') }}"
                       class="btn btn-outline-secondary btn-sm mt-3 mt-md-0">
                        Reset
                    </a>
                </div>

                <div class="col-md-2 text-end small text-muted">
                    Company #{{ $companyId }}
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search movement rows</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="cashFlowSearch" class="form-control" placeholder="Search voucher no/type/narration/ledger...">
                        <button type="button" class="btn btn-outline-secondary" id="cashFlowSearchClear">Clear</button>
                    </div>
                </div>
            </form>

            @if($cashAccounts->isEmpty())
                <div class="alert alert-warning mt-3 mb-0 small">
                    No cash / bank accounts detected. Configure them using
                    <code>account.type = 'bank'</code> or set
                    <code>accounting.cashflow_cash_account_types</code> /
                    <code>accounting.cashflow_cash_group_codes</code> in
                    <code>config/accounting.php</code>.
                </div>
            @endif
        </div>
    </div>

    @if($cashAccounts->count())
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="small text-muted mb-1">Cash/Bank Ledgers Used</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($cashAccounts as $acc)
                        <a href="{{ route('accounting.reports.ledger', [
                            'account_id' => $acc->id,
                            'from_date' => optional($fromDate)->toDateString(),
                            'to_date' => optional($toDate)->toDateString(),
                        ]) }}"
                           class="badge rounded-pill text-bg-light text-decoration-none border">
                            {{ $acc->code }} - {{ $acc->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body py-2">
                    <div class="small text-muted">Opening cash &amp; bank</div>
                    <div class="fw-semibold">
                        {{ number_format($openingCash, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body py-2">
                    <div class="small text-muted">Total inflow</div>
                    <div class="fw-semibold">
                        {{ number_format($totalInflow, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body py-2">
                    <div class="small text-muted">Total outflow</div>
                    <div class="fw-semibold">
                        {{ number_format($totalOutflow, 2) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-dark">
                <div class="card-body py-2">
                    <div class="small text-muted">Closing cash &amp; bank</div>
                    <div class="fw-semibold">
                        {{ number_format($closingCash, 2) }}
                        <span class="small text-muted d-block">
                            (Net change: {{ number_format($netChange, 2) }})
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary by voucher type --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">Cash flow by voucher type</div>
            <div class="small text-muted">
                Contra vouchers are ignored (internal cash/bank transfers).
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Voucher Type</th>
                            <th class="text-end" style="width: 30%;">Inflow</th>
                            <th class="text-end" style="width: 30%;">Outflow</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($typeRows as $row)
                            <tr>
                                <td class="small text-uppercase">{{ $row['voucher_type'] }}</td>
                                <td class="small text-end">{{ number_format($row['inflow'], 2) }}</td>
                                <td class="small text-end">{{ number_format($row['outflow'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="small text-muted text-center py-2">
                                    No cash / bank movements found in this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($typeRows))
                        <tfoot>
                            <tr class="table-dark text-white fw-semibold">
                                <td class="small text-end">Total</td>
                                <td class="small text-end">{{ number_format($totalInflow, 2) }}</td>
                                <td class="small text-end">{{ number_format($totalOutflow, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div id="cashFlowNoMatch" class="alert alert-warning d-none py-2">
        No cash-flow movement rows match your search text.
    </div>

    {{-- Detailed cash/bank movements --}}
    <div class="card">
        <div class="card-header py-2">
            <div class="fw-semibold small">Cash / Bank movements (detail)</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%;">Date</th>
                            <th style="width: 14%;">Voucher No</th>
                            <th style="width: 14%;">Type</th>
                            <th style="width: 20%;">Ledger</th>
                            <th style="width: 24%;">Narration</th>
                            <th class="text-end" style="width: 7%;">Debit</th>
                            <th class="text-end" style="width: 7%;">Credit</th>
                            <th class="text-end" style="width: 8%;">Net (In / Out)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $row)
                            @php
                                $delta = $row['inflow'] - $row['outflow'];
                                $searchText = strtolower(trim(
                                    ($row['voucher_no'] ?? '') . ' ' .
                                    ($row['voucher_type'] ?? '') . ' ' .
                                    ($row['narration'] ?? '') . ' ' .
                                    ($row['account_code'] ?? '') . ' ' .
                                    ($row['account_name'] ?? '')
                                ));
                            @endphp
                            <tr class="cash-flow-detail-row" data-search-text="{{ $searchText }}">
                                <td class="small">{{ \Illuminate\Support\Carbon::parse($row['date'])->toDateString() }}</td>
                                <td class="small">
                                    <a href="{{ route('accounting.vouchers.show', $row['voucher_id']) }}" class="text-decoration-none">
                                        {{ $row['voucher_no'] }}
                                    </a>
                                </td>
                                <td class="small text-uppercase">{{ $row['voucher_type'] }}</td>
                                <td class="small">
                                    <a href="{{ route('accounting.reports.ledger', [
                                        'account_id' => $row['account_id'],
                                        'from_date' => optional($fromDate)->toDateString(),
                                        'to_date' => optional($toDate)->toDateString(),
                                    ]) }}" class="text-decoration-none">
                                        {{ $row['account_code'] }} - {{ $row['account_name'] }}
                                    </a>
                                </td>
                                <td class="small">{{ $row['narration'] }}</td>
                                <td class="small text-end">{{ number_format($row['debit'], 2) }}</td>
                                <td class="small text-end">{{ number_format($row['credit'], 2) }}</td>
                                <td class="small text-end">
                                    @if($delta > 0)
                                        +{{ number_format($delta, 2) }}
                                    @elseif($delta < 0)
                                        -{{ number_format(abs($delta), 2) }}
                                    @else
                                        0.00
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="small text-muted text-center py-2">
                                    No movements for selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('cashFlowSearch');
    const clearBtn = document.getElementById('cashFlowSearchClear');
    const noMatch = document.getElementById('cashFlowNoMatch');
    const rows = Array.from(document.querySelectorAll('.cash-flow-detail-row'));

    function applySearch() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const txt = row.dataset.searchText || '';
            const match = !query || txt.includes(query);
            row.classList.toggle('d-none', !match);
            if (match) visible += 1;
        });

        if (noMatch) {
            noMatch.classList.toggle('d-none', !(query && visible === 0));
        }
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
