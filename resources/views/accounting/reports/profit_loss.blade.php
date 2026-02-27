@extends('layouts.erp')

@section('title', 'Profit & Loss')

@section('content')
@php
    $ledgerFrom = optional($fromDate)->toDateString();
    $ledgerTo = optional($toDate)->toDateString();
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">Profit &amp; Loss</h1>
            <div class="small text-muted">Period: {{ $ledgerFrom }} to {{ $ledgerTo }}</div>
        </div>
        <div class="small text-muted">Company #{{ $companyId }}</div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date"
                           name="from_date"
                           value="{{ request('from_date', $ledgerFrom) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date"
                           name="to_date"
                           value="{{ request('to_date', $ledgerTo) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                    <a href="{{ route('accounting.reports.profit-loss') }}" class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>

                    <a href="{{ route('accounting.reports.profit-loss', array_merge(request()->all(), ['export' => 'csv'])) }}"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </div>

                <div class="col-md-7">
                    <label class="form-label form-label-sm">Quick ledger search (code/name)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="plLedgerSearch" class="form-control" placeholder="Filter income and expense ledgers...">
                        <button type="button" class="btn btn-outline-secondary" id="plLedgerSearchClear">Clear</button>
                    </div>
                </div>

                <div class="col-md-5 d-flex justify-content-md-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="plExpandAllGroups">
                        <i class="bi bi-arrows-expand"></i> Expand all groups
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="plCollapseAllGroups">
                        <i class="bi bi-arrows-collapse"></i> Collapse all groups
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100 border-success-subtle">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Total Income</div>
                    <div class="h5 mb-0 text-success-emphasis">{{ number_format($totalIncome, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-danger-subtle">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Total Expense</div>
                    <div class="h5 mb-0 text-danger-emphasis">{{ number_format($totalExpense, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 {{ $profit >= 0 ? 'border-success' : 'border-danger' }}">
                <div class="card-body py-3">
                    <div class="small text-muted text-uppercase">Net Result</div>
                    <div class="h5 mb-0 {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $profit >= 0 ? 'Profit' : 'Loss' }}: {{ number_format(abs($profit), 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="plNoMatchAlert" class="alert alert-warning d-none py-2">
        No ledgers match the search text.
    </div>

    <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">
                Posted vouchers in selected period
            </div>
            <div class="small text-muted">
                Click a ledger to open detailed ledger report
            </div>
        </div>

        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-2">Income</h6>

                    @forelse($incomeGroups as $g)
                        @php
                            $groupKey = 'income-' . ($g['group']->id ?? 'x' . $loop->index);
                            $collapseId = 'pl_income_' . ($g['group']->id ?? 'x' . $loop->index);
                        @endphp
                        <div class="border rounded mb-3 pl-group" data-group-key="{{ $groupKey }}">
                            <div class="bg-light px-3 py-2 small fw-semibold d-flex align-items-center justify-content-between">
                                <div>
                                    <button type="button"
                                            class="btn btn-link btn-sm text-decoration-none p-0 me-1 pl-group-toggle"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $collapseId }}"
                                            aria-controls="{{ $collapseId }}"
                                            aria-expanded="true">
                                        <i class="bi bi-dash-square"></i>
                                    </button>
                                    {{ $g['group']->name }}
                                </div>
                                <span class="text-muted">{{ count($g['accounts']) }} ledgers</span>
                            </div>

                            <div id="{{ $collapseId }}" class="collapse show pl-group-collapse" data-group-key="{{ $groupKey }}">
                                <div class="p-2">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @foreach($g['accounts'] as $row)
                                                <tr class="pl-ledger-row"
                                                    data-group-key="{{ $groupKey }}"
                                                    data-ledger-text="{{ strtolower(trim(($row['account']->code ?? '') . ' ' . ($row['account']->name ?? ''))) }}">
                                                    <td class="small">
                                                        <a href="{{ route('accounting.reports.ledger', array_filter([
                                                            'account_id' => $row['account']->id,
                                                            'from_date' => $ledgerFrom,
                                                            'to_date' => $ledgerTo,
                                                        ])) }}"
                                                           class="pl-ledger-link text-decoration-none">
                                                            @if(!empty($row['account']->code))
                                                                <span class="badge bg-body-secondary text-body me-1">{{ $row['account']->code }}</span>
                                                            @endif
                                                            {{ $row['account']->name }}
                                                            <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                                        </a>
                                                    </td>
                                                    <td class="small text-end">{{ number_format($row['amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr class="table-light fw-semibold pl-group-total" data-group-key="{{ $groupKey }}">
                                                <td class="small text-end">Total {{ $g['group']->name }}</td>
                                                <td class="small text-end">{{ number_format($g['total'], 2) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">No income entries for the period.</div>
                    @endforelse
                </div>

                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-2">Expense</h6>

                    @forelse($expenseGroups as $g)
                        @php
                            $groupKey = 'expense-' . ($g['group']->id ?? 'x' . $loop->index);
                            $collapseId = 'pl_expense_' . ($g['group']->id ?? 'x' . $loop->index);
                        @endphp
                        <div class="border rounded mb-3 pl-group" data-group-key="{{ $groupKey }}">
                            <div class="bg-light px-3 py-2 small fw-semibold d-flex align-items-center justify-content-between">
                                <div>
                                    <button type="button"
                                            class="btn btn-link btn-sm text-decoration-none p-0 me-1 pl-group-toggle"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $collapseId }}"
                                            aria-controls="{{ $collapseId }}"
                                            aria-expanded="true">
                                        <i class="bi bi-dash-square"></i>
                                    </button>
                                    {{ $g['group']->name }}
                                </div>
                                <span class="text-muted">{{ count($g['accounts']) }} ledgers</span>
                            </div>

                            <div id="{{ $collapseId }}" class="collapse show pl-group-collapse" data-group-key="{{ $groupKey }}">
                                <div class="p-2">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @foreach($g['accounts'] as $row)
                                                <tr class="pl-ledger-row"
                                                    data-group-key="{{ $groupKey }}"
                                                    data-ledger-text="{{ strtolower(trim(($row['account']->code ?? '') . ' ' . ($row['account']->name ?? ''))) }}">
                                                    <td class="small">
                                                        <a href="{{ route('accounting.reports.ledger', array_filter([
                                                            'account_id' => $row['account']->id,
                                                            'from_date' => $ledgerFrom,
                                                            'to_date' => $ledgerTo,
                                                        ])) }}"
                                                           class="pl-ledger-link text-decoration-none">
                                                            @if(!empty($row['account']->code))
                                                                <span class="badge bg-body-secondary text-body me-1">{{ $row['account']->code }}</span>
                                                            @endif
                                                            {{ $row['account']->name }}
                                                            <i class="bi bi-box-arrow-up-right small ms-1"></i>
                                                        </a>
                                                    </td>
                                                    <td class="small text-end">{{ number_format($row['amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr class="table-light fw-semibold pl-group-total" data-group-key="{{ $groupKey }}">
                                                <td class="small text-end">Total {{ $g['group']->name }}</td>
                                                <td class="small text-end">{{ number_format($g['total'], 2) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">No expense entries for the period.</div>
                    @endforelse
                </div>

                <div class="col-12">
                    <div class="alert {{ $profit >= 0 ? 'alert-success' : 'alert-danger' }} mb-0">
                        <div class="fw-semibold">
                            {{ $profit >= 0 ? 'Profit' : 'Loss' }}: {{ number_format(abs($profit), 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.pl-ledger-link {
    color: var(--bs-body-color);
}

.pl-ledger-link:hover {
    color: var(--bs-primary);
}

.pl-ledger-row:hover td {
    background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
}

.pl-group-toggle {
    color: var(--bs-body-color);
}

.pl-group-toggle:hover {
    color: var(--bs-primary);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('plLedgerSearch');
    const clearBtn = document.getElementById('plLedgerSearchClear');
    const noMatchAlert = document.getElementById('plNoMatchAlert');
    const expandAllBtn = document.getElementById('plExpandAllGroups');
    const collapseAllBtn = document.getElementById('plCollapseAllGroups');

    const groupCollapses = Array.from(document.querySelectorAll('.pl-group-collapse'));
    const groupHeaders = Array.from(document.querySelectorAll('.pl-group'));
    const groupToggles = Array.from(document.querySelectorAll('.pl-group-toggle'));
    const ledgerRows = Array.from(document.querySelectorAll('.pl-ledger-row'));
    const groupTotals = Array.from(document.querySelectorAll('.pl-group-total'));

    function syncGroupIcons() {
        groupToggles.forEach((toggle) => {
            const target = toggle.getAttribute('data-bs-target');
            if (!target) return;
            const section = document.querySelector(target);
            const icon = toggle.querySelector('i');
            if (!section || !icon) return;

            const open = section.classList.contains('show');
            icon.className = open ? 'bi bi-dash-square' : 'bi bi-plus-square';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function setAllExpanded(expanded) {
        groupCollapses.forEach((section) => {
            const c = bootstrap.Collapse.getOrCreateInstance(section, { toggle: false });
            if (expanded) c.show();
            else c.hide();
        });
        setTimeout(syncGroupIcons, 40);
    }

    function applySearch() {
        if (!searchInput) return;
        const query = (searchInput.value || '').trim().toLowerCase();
        const visibleGroups = new Set();
        let visibleRows = 0;

        ledgerRows.forEach((row) => {
            const txt = (row.dataset.ledgerText || row.textContent || '').toLowerCase();
            const match = !query || txt.includes(query);
            row.classList.toggle('d-none', !match);
            if (match) {
                visibleRows += 1;
                const gk = row.dataset.groupKey;
                if (gk) visibleGroups.add(gk);
            }
        });

        if (query) setAllExpanded(true);

        groupHeaders.forEach((block) => {
            const gk = block.dataset.groupKey;
            const visible = !query || (gk && visibleGroups.has(gk));
            block.classList.toggle('d-none', !visible);
        });

        groupCollapses.forEach((section) => {
            const gk = section.dataset.groupKey;
            const visible = !query || (gk && visibleGroups.has(gk));
            section.classList.toggle('d-none', !visible);
        });

        groupTotals.forEach((row) => {
            row.classList.toggle('d-none', !!query);
        });

        if (noMatchAlert) {
            noMatchAlert.classList.toggle('d-none', !(query && visibleRows === 0));
        }

        if (!query) {
            groupCollapses.forEach((section) => section.classList.remove('d-none'));
            syncGroupIcons();
        }
    }

    groupCollapses.forEach((section) => {
        section.addEventListener('shown.bs.collapse', syncGroupIcons);
        section.addEventListener('hidden.bs.collapse', syncGroupIcons);
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => setAllExpanded(true));
    }
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => setAllExpanded(false));
    }

    if (searchInput) {
        searchInput.addEventListener('input', applySearch);
    }
    if (clearBtn && searchInput) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            applySearch();
            searchInput.focus();
        });
    }

    syncGroupIcons();
});
</script>
@endpush
