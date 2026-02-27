@extends('layouts.erp')

@section('title', 'Day Book')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Day Book</h1>

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

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Voucher type</label>
                    <select name="voucher_type" class="form-select form-select-sm">
                        <option value="">-- All --</option>
                        @foreach($voucherTypes as $vt)
                            <option value="{{ $vt }}" @selected($type === $vt)>
                                {{ strtoupper($vt) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Project (optional)</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected((string) $projectId === (string) $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search (voucher no/type/narration)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="dbSearch" class="form-control" placeholder="Filter visible vouchers...">
                        <button type="button" class="btn btn-outline-secondary" id="dbSearchClear">Clear</button>
                    </div>
                </div>

                <div class="col-md-6 d-flex flex-wrap align-items-center gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                    <a href="{{ route('accounting.reports.day-book') }}" class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>

                    <a href="{{ route('accounting.reports.day-book', array_merge(request()->all(), ['export' => 'csv'])) }}"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>

                    <button type="button" class="btn btn-outline-secondary btn-sm ms-md-2" id="dbExpandAll">
                        <i class="bi bi-arrows-expand"></i> Expand all
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="dbCollapseAll">
                        <i class="bi bi-arrows-collapse"></i> Collapse all
                    </button>

                    <span class="small text-muted ms-md-auto">
                        Company #{{ $companyId }}
                    </span>
                </div>
            </form>
        </div>
    </div>

    @if(($unbalancedCount ?? 0) > 0)
        <div class="alert alert-warning">
            <div class="small">
                Warning: <strong>{{ $unbalancedCount }}</strong> voucher(s) in this range appear to be <strong>unbalanced</strong> (Debit ≠ Credit).
                Check the <a href="{{ route('accounting.reports.unbalanced-vouchers', array_merge(request()->all(), ['from_date' => optional($fromDate)->toDateString(), 'to_date' => optional($toDate)->toDateString()])) }}">Unbalanced Vouchers</a> report.
            </div>
        </div>
    @endif

    <div id="dbNoMatchAlert" class="alert alert-warning d-none py-2">
        No vouchers match your search text.
    </div>

    <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">
                Vouchers from {{ optional($fromDate)->toDateString() }} to {{ optional($toDate)->toDateString() }}
                @if($type)
                    · Type: {{ strtoupper($type) }}
                @endif
                @if($projectId)
                    · Project filter applied
                @endif
            </div>
            <div class="small text-muted">
                Posted vouchers only.
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 4%"></th>
                            <th style="width: 9%">Date</th>
                            <th style="width: 12%">Voucher No</th>
                            <th style="width: 10%">Type</th>
                            <th>Description</th>
                            <th style="width: 10%" class="text-end">Debit</th>
                            <th style="width: 10%" class="text-end">Credit</th>
                            <th style="width: 10%" class="text-end">Diff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $grandDebit = 0.0;
                            $grandCredit = 0.0;
                        @endphp

                        @if(count($vouchers))
                            @foreach($vouchers as $voucher)
                                @php
                                    $debitTotal = (float) $voucher->lines->sum('debit');
                                    $creditTotal = (float) $voucher->lines->sum('credit');
                                    $diff = $debitTotal - $creditTotal;
                                    $grandDebit += $debitTotal;
                                    $grandCredit += $creditTotal;
                                    $collapseId = 'db_lines_' . $voucher->id;
                                    $searchText = strtolower(trim(
                                        optional($voucher->voucher_date)->toDateString() . ' ' .
                                        ($voucher->voucher_no ?? '') . ' ' .
                                        ($voucher->voucher_type ?? '') . ' ' .
                                        ($voucher->narration ?? '') . ' ' .
                                        ($voucher->reference ?? '')
                                    ));
                                @endphp
                                <tr class="db-voucher-row {{ abs($diff) > 0.01 ? 'table-warning' : '' }}" data-search-text="{{ $searchText }}">
                                    <td class="small text-center">
                                        <button type="button"
                                                class="btn btn-link btn-sm text-decoration-none p-0 db-voucher-toggle"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#{{ $collapseId }}"
                                                aria-controls="{{ $collapseId }}"
                                                aria-expanded="false">
                                            <i class="bi bi-plus-square"></i>
                                        </button>
                                    </td>
                                    <td class="small">{{ optional($voucher->voucher_date)->toDateString() }}</td>
                                    <td class="small fw-semibold">
                                        <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="text-decoration-none">
                                            {{ $voucher->voucher_no }}
                                        </a>
                                    </td>
                                    <td class="small text-uppercase">{{ $voucher->voucher_type }}</td>
                                    <td class="small">
                                        <div class="fw-semibold">{{ $voucher->narration ?: '-' }}</div>
                                        @if($voucher->reference)
                                            <div class="text-muted">Ref: {{ $voucher->reference }}</div>
                                        @endif
                                        @if($voucher->project)
                                            <div class="text-muted">Project: {{ $voucher->project->code }} - {{ $voucher->project->name }}</div>
                                        @endif
                                    </td>
                                    <td class="small text-end">{{ number_format($debitTotal, 2) }}</td>
                                    <td class="small text-end">{{ number_format($creditTotal, 2) }}</td>
                                    <td class="small text-end fw-semibold">{{ number_format($diff, 2) }}</td>
                                </tr>

                                <tr class="db-lines-wrap">
                                    <td colspan="8" class="p-0">
                                        <div id="{{ $collapseId }}" class="collapse db-lines-collapse">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="small">Account</th>
                                                            <th class="small text-end" style="width: 15%">Debit</th>
                                                            <th class="small text-end" style="width: 15%">Credit</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($voucher->lines as $line)
                                                            <tr>
                                                                <td class="small">
                                                                    @if($line->account)
                                                                        <a href="{{ route('accounting.reports.ledger', array_filter([
                                                                            'account_id' => $line->account->id,
                                                                            'from_date' => optional($fromDate)->toDateString(),
                                                                            'to_date' => optional($toDate)->toDateString(),
                                                                            'project_id' => $projectId,
                                                                        ])) }}" class="text-decoration-none">
                                                                            {{ $line->account->name }}
                                                                        </a>
                                                                        <span class="text-muted">({{ $line->account->code }})</span>
                                                                    @else
                                                                        <span class="text-muted">Unknown account</span>
                                                                    @endif
                                                                    @if($line->description)
                                                                        <div class="text-muted">{{ $line->description }}</div>
                                                                    @endif
                                                                </td>
                                                                <td class="small text-end">{{ number_format($line->debit, 2) }}</td>
                                                                <td class="small text-end">{{ number_format($line->credit, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="8" class="text-center small text-muted py-2">
                                    No vouchers found for the selected filters.
                                </td>
                            </tr>
                        @endif

                        @if(count($vouchers))
                            <tr class="table-dark text-white fw-semibold db-grand-total-row">
                                <td colspan="5" class="text-end small">Grand Total</td>
                                <td class="small text-end">{{ number_format($grandDebit, 2) }}</td>
                                <td class="small text-end">{{ number_format($grandCredit, 2) }}</td>
                                <td class="small text-end">{{ number_format($grandDebit - $grandCredit, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.db-voucher-toggle {
    color: var(--bs-body-color);
}

.db-voucher-toggle:hover {
    color: var(--bs-primary);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('dbSearch');
    const clearBtn = document.getElementById('dbSearchClear');
    const noMatchAlert = document.getElementById('dbNoMatchAlert');
    const expandAllBtn = document.getElementById('dbExpandAll');
    const collapseAllBtn = document.getElementById('dbCollapseAll');

    const voucherRows = Array.from(document.querySelectorAll('.db-voucher-row'));
    const lineRows = Array.from(document.querySelectorAll('.db-lines-wrap'));
    const collapses = Array.from(document.querySelectorAll('.db-lines-collapse'));
    const toggles = Array.from(document.querySelectorAll('.db-voucher-toggle'));
    const grandTotalRow = document.querySelector('.db-grand-total-row');

    function syncIcons() {
        toggles.forEach((toggle) => {
            const target = toggle.getAttribute('data-bs-target');
            const icon = toggle.querySelector('i');
            if (!target || !icon) return;
            const section = document.querySelector(target);
            if (!section) return;
            const open = section.classList.contains('show');
            icon.className = open ? 'bi bi-dash-square' : 'bi bi-plus-square';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function setAllExpanded(expanded) {
        collapses.forEach((section) => {
            const c = bootstrap.Collapse.getOrCreateInstance(section, { toggle: false });
            if (expanded) c.show();
            else c.hide();
        });
        setTimeout(syncIcons, 40);
    }

    function applySearch() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        voucherRows.forEach((row, idx) => {
            const txt = (row.dataset.searchText || row.textContent || '').toLowerCase();
            const match = !query || txt.includes(query);
            row.classList.toggle('d-none', !match);
            if (lineRows[idx]) {
                lineRows[idx].classList.toggle('d-none', !match);
            }
            if (match) visibleCount += 1;
        });

        if (grandTotalRow) {
            grandTotalRow.classList.toggle('d-none', !!query);
        }

        if (noMatchAlert) {
            noMatchAlert.classList.toggle('d-none', !(query && visibleCount === 0));
        }

        if (query) {
            setAllExpanded(true);
        }
    }

    collapses.forEach((section) => {
        section.addEventListener('shown.bs.collapse', syncIcons);
        section.addEventListener('hidden.bs.collapse', syncIcons);
    });

    if (searchInput) searchInput.addEventListener('input', applySearch);
    if (clearBtn && searchInput) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applySearch();
            searchInput.focus();
        });
    }
    if (expandAllBtn) expandAllBtn.addEventListener('click', () => setAllExpanded(true));
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', () => setAllExpanded(false));

    syncIcons();
});
</script>
@endpush
