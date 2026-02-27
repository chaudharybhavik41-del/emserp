@extends('layouts.erp')

@section('title', 'Balance Sheet')

@section('content')
@php
    // Profit/Loss balancing logic: positive = Profit (credit) goes to Liabilities side
    // negative = Loss (debit) goes to Assets side
    $plIsProfit = $plBalance > 0;
    $plAmount = abs($plBalance);
    $ledgerTo = optional($asOfDate)->toDateString();
    $ledgerFrom = optional($asOfDate)->copy()->startOfMonth()->toDateString();
    $assetsDrCr = $totalAssets >= 0 ? 'Dr' : 'Cr';
    $liabDrCr = $totalLiabilities >= 0 ? 'Cr' : 'Dr';
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Balance Sheet</h1>
            <div class="small text-muted">As on {{ $ledgerTo }}</div>
        </div>
        <div class="small text-muted">Company #{{ $companyId }}</div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">As on date</label>
                    <input type="date"
                           name="as_of_date"
                           value="{{ request('as_of_date', optional($asOfDate)->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                    <a href="{{ route('accounting.reports.balance-sheet') }}" class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>

                    <a href="{{ route('accounting.reports.balance-sheet', array_merge(request()->all(), ['export' => 'csv'])) }}"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </div>

                <div class="col-md-5">
                    <label class="form-label form-label-sm">Quick ledger search (code/name)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text"
                               id="bsLedgerSearch"
                               class="form-control"
                               placeholder="Type to filter ledger rows on both sides...">
                        <button type="button" class="btn btn-outline-secondary" id="bsLedgerSearchClear">Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 bs-stat-card">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Total Assets</div>
                    <div class="h5 mb-0">{{ number_format(abs($totalAssets), 2) }} <span class="fs-6">{{ $assetsDrCr }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 bs-stat-card">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Total Liabilities</div>
                    <div class="h5 mb-0">{{ number_format(abs($totalLiabilities), 2) }} <span class="fs-6">{{ $liabDrCr }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 bs-stat-card">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Balancing P/L</div>
                    <div class="h5 mb-0">{{ number_format($plAmount, 2) }}
                        <span class="badge {{ $plIsProfit ? 'text-bg-success' : 'text-bg-warning text-dark' }}">
                            {{ $plIsProfit ? 'Profit (Cr)' : 'Loss (Dr)' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 bs-stat-card">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Report Period</div>
                    <div class="h6 mb-0">{{ $ledgerFrom }} to {{ $ledgerTo }}</div>
                    <div class="small text-muted">Drill-down defaults to this period</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <div class="small">
            This Balance Sheet is generated from <strong>Opening Balances</strong> + <strong>Posted Vouchers</strong> up to the selected date.
            Income &amp; Expense ledgers are excluded and a Profit/Loss balancing line is shown.
            Amounts show <strong>Dr/Cr</strong> to make contra balances visible.
            Click any ledger to open the detailed ledger statement.
        </div>
    </div>

    <div id="bsNoMatchAlert" class="alert alert-warning d-none py-2">
        No ledgers match the search text. Clear search to see all rows again.
    </div>

    <div class="d-flex justify-content-end gap-2 mb-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bsExpandAllGroups">
            <i class="bi bi-arrows-expand"></i> Expand all groups
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bsCollapseAllGroups">
            <i class="bi bi-arrows-collapse"></i> Collapse all groups
        </button>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2 fw-semibold small">Assets</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Particulars</th>
                                    <th style="width: 30%" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            @foreach($assetGroups as $g)
                                @php
                                    $groupKey = 'asset-' . ($g['group']->id ?? '0');
                                    $collapseId = 'bs_asset_group_' . ($g['group']->id ?? ('x_' . $loop->index));
                                @endphp
                                <tbody class="bs-group-block" data-group-key="{{ $groupKey }}">
                                    <tr class="table-secondary bs-group-header" data-group-key="{{ $groupKey }}">
                                        <td class="small fw-semibold" colspan="2">
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-decoration-none p-0 me-1 bs-group-toggle"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-controls="{{ $collapseId }}"
                                                    aria-expanded="true">
                                                <i class="bi bi-dash-square"></i>
                                            </button>
                                            {{ $g['group']->name }}
                                            <span class="text-muted">({{ count($g['accounts']) }} ledgers)</span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody id="{{ $collapseId }}" class="collapse show bs-group-collapse" data-group-key="{{ $groupKey }}">
                                    @foreach($g['accounts'] as $row)
                                        @php
                                            $amt = (float) $row['amount'];
                                            $drcr = $amt >= 0 ? 'Dr' : 'Cr';
                                        @endphp
                                        <tr class="bs-ledger-row" data-group-key="{{ $groupKey }}"
                                            data-ledger-text="{{ strtolower(trim(($row['account']->code ?? '') . ' ' . ($row['account']->name ?? ''))) }}">
                                            <td class="small ps-3">
                                                <a href="{{ route('accounting.reports.ledger', array_filter([
                                                    'account_id' => $row['account']->id,
                                                    'from_date' => $ledgerFrom,
                                                    'to_date' => $ledgerTo,
                                                ])) }}"
                                                   class="bs-ledger-link text-decoration-none">
                                                    @if(!empty($row['account']->code))
                                                        <span class="badge bg-body-secondary text-body me-1">{{ $row['account']->code }}</span>
                                                    @endif
                                                    <span>{{ $row['account']->name }}</span>
                                                    <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                                </a>
                                            </td>
                                            <td class="small text-end">
                                                {{ number_format(abs($amt), 2) }} <span class="fw-semibold">{{ $drcr }}</span>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @php
                                        $gt = (float) $g['total'];
                                        $gDrCr = $gt >= 0 ? 'Dr' : 'Cr';
                                    @endphp
                                    <tr class="table-light fw-semibold bs-group-total" data-group-key="{{ $groupKey }}">
                                        <td class="small text-end">Total {{ $g['group']->name }}</td>
                                        <td class="small text-end">
                                            {{ number_format(abs($gt), 2) }} {{ $gDrCr }}
                                        </td>
                                    </tr>
                                </tbody>
                            @endforeach
                            <tbody>
                                @if(empty($assetGroups))
                                    <tr class="bs-static-row">
                                        <td colspan="2" class="text-center small text-muted py-3">No asset-side ledgers found.</td>
                                    </tr>
                                @endif

                                @if(!empty($assetGroups) && !$plIsProfit && $plAmount > 0.005)
                                    <tr class="table-warning fw-semibold bs-static-row">
                                        <td class="small">Loss (Balancing)</td>
                                        <td class="small text-end">{{ number_format($plAmount, 2) }} Dr</td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="table-dark text-white fw-semibold bs-static-row">
                                    <td class="small text-end">Total Assets</td>
                                    <td class="small text-end">{{ number_format(abs($totalAssets), 2) }} {{ $assetsDrCr }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2 fw-semibold small">Liabilities &amp; Equity</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Particulars</th>
                                    <th style="width: 30%" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            @foreach($liabilityGroups as $g)
                                @php
                                    $groupKey = 'liability-' . ($g['group']->id ?? '0');
                                    $collapseId = 'bs_liability_group_' . ($g['group']->id ?? ('x_' . $loop->index));
                                @endphp
                                <tbody class="bs-group-block" data-group-key="{{ $groupKey }}">
                                    <tr class="table-secondary bs-group-header" data-group-key="{{ $groupKey }}">
                                        <td class="small fw-semibold" colspan="2">
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-decoration-none p-0 me-1 bs-group-toggle"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-controls="{{ $collapseId }}"
                                                    aria-expanded="true">
                                                <i class="bi bi-dash-square"></i>
                                            </button>
                                            {{ $g['group']->name }}
                                            <span class="text-muted">({{ count($g['accounts']) }} ledgers)</span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody id="{{ $collapseId }}" class="collapse show bs-group-collapse" data-group-key="{{ $groupKey }}">
                                    @foreach($g['accounts'] as $row)
                                        @php
                                            $amt = (float) $row['amount'];
                                            // In controller, liabilities amounts are stored as credit-positive
                                            $drcr = $amt >= 0 ? 'Cr' : 'Dr';
                                        @endphp
                                        <tr class="bs-ledger-row" data-group-key="{{ $groupKey }}"
                                            data-ledger-text="{{ strtolower(trim(($row['account']->code ?? '') . ' ' . ($row['account']->name ?? ''))) }}">
                                            <td class="small ps-3">
                                                <a href="{{ route('accounting.reports.ledger', array_filter([
                                                    'account_id' => $row['account']->id,
                                                    'from_date' => $ledgerFrom,
                                                    'to_date' => $ledgerTo,
                                                ])) }}"
                                                   class="bs-ledger-link text-decoration-none">
                                                    @if(!empty($row['account']->code))
                                                        <span class="badge bg-body-secondary text-body me-1">{{ $row['account']->code }}</span>
                                                    @endif
                                                    <span>{{ $row['account']->name }}</span>
                                                    <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                                </a>
                                            </td>
                                            <td class="small text-end">
                                                {{ number_format(abs($amt), 2) }} <span class="fw-semibold">{{ $drcr }}</span>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @php
                                        $gt = (float) $g['total'];
                                        $gDrCr = $gt >= 0 ? 'Cr' : 'Dr';
                                    @endphp
                                    <tr class="table-light fw-semibold bs-group-total" data-group-key="{{ $groupKey }}">
                                        <td class="small text-end">Total {{ $g['group']->name }}</td>
                                        <td class="small text-end">
                                            {{ number_format(abs($gt), 2) }} {{ $gDrCr }}
                                        </td>
                                    </tr>
                                </tbody>
                            @endforeach
                            <tbody>
                                @if(empty($liabilityGroups))
                                    <tr class="bs-static-row">
                                        <td colspan="2" class="text-center small text-muted py-3">No liability/equity-side ledgers found.</td>
                                    </tr>
                                @endif

                                @if(!empty($liabilityGroups) && $plIsProfit && $plAmount > 0.005)
                                    <tr class="table-warning fw-semibold bs-static-row">
                                        <td class="small">Profit (Balancing)</td>
                                        <td class="small text-end">{{ number_format($plAmount, 2) }} Cr</td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="table-dark text-white fw-semibold bs-static-row">
                                    <td class="small text-end">Total Liabilities</td>
                                    <td class="small text-end">{{ number_format(abs($totalLiabilities), 2) }} {{ $liabDrCr }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.bs-stat-card {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 0.35rem 0.8rem rgba(15, 23, 42, 0.04);
}

.bs-ledger-link {
    color: var(--bs-body-color);
    display: inline-flex;
    align-items: center;
    gap: .2rem;
}

.bs-ledger-link:hover {
    color: var(--bs-primary);
}

.bs-ledger-row:hover td {
    background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
}

.bs-group-toggle {
    color: var(--bs-body-color);
}

.bs-group-toggle:hover {
    color: var(--bs-primary);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('bsLedgerSearch');
    const clearBtn = document.getElementById('bsLedgerSearchClear');
    const noMatchAlert = document.getElementById('bsNoMatchAlert');
    const expandAllBtn = document.getElementById('bsExpandAllGroups');
    const collapseAllBtn = document.getElementById('bsCollapseAllGroups');

    if (!searchInput) return;

    const ledgerRows = Array.from(document.querySelectorAll('.bs-ledger-row'));
    const groupHeaders = Array.from(document.querySelectorAll('.bs-group-header'));
    const groupCollapses = Array.from(document.querySelectorAll('.bs-group-collapse'));
    const groupToggles = Array.from(document.querySelectorAll('.bs-group-toggle'));
    const groupTotals = Array.from(document.querySelectorAll('.bs-group-total'));
    const staticRows = Array.from(document.querySelectorAll('.bs-static-row'));

    function syncGroupToggleIcons() {
        groupToggles.forEach((toggle) => {
            const target = toggle.getAttribute('data-bs-target');
            if (!target) return;

            const collapseEl = document.querySelector(target);
            const icon = toggle.querySelector('i');
            if (!collapseEl || !icon) return;

            const isOpen = collapseEl.classList.contains('show');
            icon.className = isOpen ? 'bi bi-dash-square' : 'bi bi-plus-square';
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function setAllGroupsExpanded(expanded) {
        groupCollapses.forEach((collapseEl) => {
            const collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
            if (expanded) {
                collapse.show();
            } else {
                collapse.hide();
            }
        });
        setTimeout(syncGroupToggleIcons, 40);
    }

    function applyLedgerSearch() {
        const query = (searchInput.value || '').trim().toLowerCase();
        const visibleGroups = new Set();
        let visibleLedgerRows = 0;

        ledgerRows.forEach((row) => {
            const haystack = (row.dataset.ledgerText || row.textContent || '').toLowerCase();
            const matches = !query || haystack.includes(query);
            row.classList.toggle('d-none', !matches);

            if (matches) {
                visibleLedgerRows += 1;
                const groupKey = row.dataset.groupKey;
                if (groupKey) visibleGroups.add(groupKey);
            }
        });

        if (query) {
            setAllGroupsExpanded(true);
        }

        groupHeaders.forEach((row) => {
            const groupKey = row.dataset.groupKey;
            const visible = !query || (groupKey && visibleGroups.has(groupKey));
            row.classList.toggle('d-none', !visible);
        });

        groupCollapses.forEach((section) => {
            const groupKey = section.dataset.groupKey;
            const visible = !query || (groupKey && visibleGroups.has(groupKey));
            section.classList.toggle('d-none', !visible);
        });

        groupTotals.forEach((row) => {
            const groupKey = row.dataset.groupKey;
            const visible = (!query || (groupKey && visibleGroups.has(groupKey))) && !query;
            row.classList.toggle('d-none', !visible);
        });

        staticRows.forEach((row) => {
            row.classList.toggle('d-none', !!query);
        });

        if (noMatchAlert) {
            noMatchAlert.classList.toggle('d-none', !(query && visibleLedgerRows === 0));
        }

        if (!query) {
            groupCollapses.forEach((section) => section.classList.remove('d-none'));
            syncGroupToggleIcons();
        }
    }

    searchInput.addEventListener('input', applyLedgerSearch);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applyLedgerSearch();
            searchInput.focus();
        });
    }

    groupCollapses.forEach((section) => {
        section.addEventListener('shown.bs.collapse', syncGroupToggleIcons);
        section.addEventListener('hidden.bs.collapse', syncGroupToggleIcons);
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            setAllGroupsExpanded(true);
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function () {
            setAllGroupsExpanded(false);
        });
    }

    syncGroupToggleIcons();
});
</script>
@endpush
