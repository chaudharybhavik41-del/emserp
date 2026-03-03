@extends('layouts.erp')

@section('title', 'Trial Balance')

@section('content')
@php
    $ledgerTo = optional($asOfDate)->toDateString();
    $ledgerFrom = optional($asOfDate)->copy()->startOfMonth()->toDateString();
    $groupCount = collect($rows)->pluck('group.id')->filter()->unique()->count();
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Trial Balance (Group-wise)</h1>
            <div class="small text-muted">As on {{ $ledgerTo }} · Posted vouchers only</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Groups</div>
                    <div class="h5 mb-0">{{ $groupCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Ledgers</div>
                    <div class="h5 mb-0">{{ count($rows) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Debit Total</div>
                    <div class="h5 mb-0">{{ number_format($grandDebit, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Credit Total</div>
                    <div class="h5 mb-0">{{ number_format($grandCredit, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">As on date</label>
                    <input type="date"
                           name="as_of_date"
                           value="{{ request('as_of_date', $ledgerTo) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <label class="form-label form-label-sm">Project (optional)</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects (Company TB) --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected((string) $projectId === (string) $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small">
                        If a project is selected, TB shows <strong>only voucher movements</strong> tagged to that project (opening balances are company-level and excluded).
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-filter"></i> Apply
                        </button>

                        <a href="{{ route('accounting.reports.trial-balance') }}"
                           class="btn btn-outline-secondary btn-sm">
                            Reset
                        </a>

                        <a href="{{ route('accounting.reports.trial-balance', array_merge(request()->all(), ['export' => 'csv'])) }}"
                           class="btn btn-outline-success btn-sm">
                            <i class="bi bi-download"></i> Export CSV
                        </a>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick ledger search (group/code/name)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="tbSearch" class="form-control" placeholder="Filter visible ledger rows...">
                        <button type="button" class="btn btn-outline-secondary" id="tbClearSearch">Clear</button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm d-block">Group controls</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="tbExpandAll">Expand all groups</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="tbCollapseAll">Collapse all groups</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(abs($difference) > 0.01)
        <div class="alert alert-danger">
            <div class="small">
                <strong>Trial Balance is not matching.</strong>
                Difference (Dr - Cr): <strong>{{ number_format($difference, 2) }}</strong>.
                This usually means some voucher(s) are unbalanced or data is incomplete.
                You can check the <strong>Unbalanced Vouchers</strong> report.
            </div>
        </div>
    @endif

    <div class="alert alert-info py-2">
        <div class="small mb-0">
            Click any ledger name to open the detailed ledger report from {{ $ledgerFrom }} to {{ $ledgerTo }}.
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">
                Trial Balance as on {{ $ledgerTo }}
                @if($projectId)
                    <span class="text-muted">(Project filtered)</span>
                @endif
            </div>
            <div class="small text-muted">Company #{{ $companyId }}</div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 26%;">Group</th>
                            <th style="width: 10%;">Account Code</th>
                            <th style="width: 34%;">Account Name</th>
                            <th style="width: 15%;" class="text-end">Debit</th>
                            <th style="width: 15%;" class="text-end">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="tbNoMatchRow" class="d-none">
                            <td colspan="5" class="text-center small text-muted py-3">No ledgers match the search text.</td>
                        </tr>
                        @php
                            $currentGroupId = null;
                            $currentGroupName = '';
                            $currentGroupNature = '';
                            $groupDebit = 0.0;
                            $groupCredit = 0.0;
                            $hasRows = count($rows) > 0;
                            $groupIndex = 0;
                        @endphp

                        @if($hasRows)
                            @foreach($rows as $row)
                                @php
                                    $group = $row['group'];
                                    $account = $row['account'];
                                    $gid = $group?->id ?? 0;
                                    $groupName = $group?->name ?? 'Ungrouped';
                                @endphp

                                @if($currentGroupId !== $gid)
                                    @if(!is_null($currentGroupId))
                                        <tr class="table-light fw-semibold tb-group-subtotal"
                                            data-group-key="{{ $groupKey }}"
                                            data-group-name="{{ strtolower($currentGroupName) }}">
                                            <td class="small text-end" colspan="3">Total {{ $currentGroupName }}</td>
                                            <td class="small text-end">{{ number_format($groupDebit, 2) }}</td>
                                            <td class="small text-end">{{ number_format($groupCredit, 2) }}</td>
                                        </tr>
                                    @endif

                                    @php
                                        $groupIndex++;
                                        $groupKey = 'tb-group-' . $groupIndex;
                                        $currentGroupId = $gid;
                                        $currentGroupName = $groupName;
                                        $currentGroupNature = $group?->nature ?? '';
                                        $groupDebit = 0.0;
                                        $groupCredit = 0.0;
                                    @endphp

                                    <tr class="table-secondary tb-group-header" data-group-key="{{ $groupKey }}" data-group-name="{{ strtolower($currentGroupName) }}">
                                        <td class="small fw-semibold" colspan="5">
                                            <button type="button"
                                                    class="btn btn-link btn-sm text-decoration-none p-0 tb-group-toggle"
                                                    data-group-key="{{ $groupKey }}"
                                                    data-expanded="true">
                                                <i class="bi bi-caret-down-fill me-1"></i>
                                                {{ $currentGroupName }}
                                            </button>
                                            @if($currentGroupNature)
                                                <span class="text-muted">({{ ucfirst($currentGroupNature) }})</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif

                                @php
                                    $groupDebit += $row['debit'];
                                    $groupCredit += $row['credit'];
                                    $searchText = strtolower(trim(($groupName ?? '') . ' ' . ($account->code ?? '') . ' ' . ($account->name ?? '')));
                                @endphp
                                <tr class="tb-account-row"
                                    data-group-key="{{ $groupKey }}"
                                    data-row-text="{{ $searchText }}">
                                    <td class="small"></td>
                                    <td class="small">{{ $account->code }}</td>
                                    <td class="small">
                                        <a href="{{ route('accounting.reports.ledger', array_filter([
                                            'account_id' => $account->id,
                                            'from_date' => $ledgerFrom,
                                            'to_date' => $ledgerTo,
                                            'project_id' => $projectId,
                                        ])) }}" class="text-decoration-none tb-ledger-link">
                                            {{ $account->name }}
                                        </a>
                                    </td>
                                    <td class="small text-end">{{ number_format($row['debit'], 2) }}</td>
                                    <td class="small text-end">{{ number_format($row['credit'], 2) }}</td>
                                </tr>
                            @endforeach

                            @if(!is_null($currentGroupId))
                                <tr class="table-light fw-semibold tb-group-subtotal"
                                    data-group-key="{{ $groupKey }}"
                                    data-group-name="{{ strtolower($currentGroupName) }}">
                                    <td class="small text-end" colspan="3">Total {{ $currentGroupName }}</td>
                                    <td class="small text-end">{{ number_format($groupDebit, 2) }}</td>
                                    <td class="small text-end">{{ number_format($groupCredit, 2) }}</td>
                                </tr>
                            @endif
                        @else
                            <tr>
                                <td colspan="5" class="text-center small text-muted py-2">
                                    No data for the selected filters.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    @if($hasRows)
                        <tfoot>
                            <tr class="table-dark fw-semibold text-white">
                                <td colspan="3" class="text-end small">Grand Total</td>
                                <td class="small text-end">{{ number_format($grandDebit, 2) }}</td>
                                <td class="small text-end">{{ number_format($grandCredit, 2) }}</td>
                            </tr>
                            <tr class="{{ abs($difference) > 0.01 ? 'table-danger' : 'table-light' }} fw-semibold">
                                <td colspan="3" class="text-end small">Difference (Dr - Cr)</td>
                                <td class="small text-end">{{ number_format($difference, 2) }}</td>
                                <td class="small"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.tb-ledger-link { color: #0f4c81; font-weight: 500; }
.tb-ledger-link:hover { color: #0b3a61; text-decoration: underline !important; }
.tb-account-row:hover td { background: #f3f8ff; }
.tb-group-toggle { color: #1f2937; font-weight: 600; }
.tb-group-toggle:hover { color: #0f4c81; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('tbSearch');
    const clearBtn = document.getElementById('tbClearSearch');
    const noMatchRow = document.getElementById('tbNoMatchRow');
    const expandAllBtn = document.getElementById('tbExpandAll');
    const collapseAllBtn = document.getElementById('tbCollapseAll');

    const headers = Array.from(document.querySelectorAll('.tb-group-header'));
    const subtotals = Array.from(document.querySelectorAll('.tb-group-subtotal'));
    const rows = Array.from(document.querySelectorAll('.tb-account-row'));
    const toggles = Array.from(document.querySelectorAll('.tb-group-toggle'));

    function groupRows(groupKey) {
        return rows.filter((row) => row.dataset.groupKey === groupKey);
    }

    function groupSubtotal(groupKey) {
        return subtotals.find((row) => row.dataset.groupKey === groupKey) || null;
    }

    function groupHeader(groupKey) {
        return headers.find((row) => row.dataset.groupKey === groupKey) || null;
    }

    function updateToggleIcon(toggle, expanded) {
        toggle.dataset.expanded = expanded ? 'true' : 'false';
        const icon = toggle.querySelector('i');
        if (!icon) return;
        icon.classList.toggle('bi-caret-down-fill', expanded);
        icon.classList.toggle('bi-caret-right-fill', !expanded);
    }

    function setGroupExpanded(groupKey, expanded) {
        const groupHasVisibleRows = groupRows(groupKey).some((row) => row.dataset.searchHidden !== 'true');
        groupRows(groupKey).forEach((row) => {
            if (row.dataset.searchHidden === 'true') {
                row.classList.add('d-none');
            } else {
                row.classList.toggle('d-none', !expanded);
            }
        });

        const subtotalRow = groupSubtotal(groupKey);
        if (subtotalRow) {
            subtotalRow.classList.toggle('d-none', !groupHasVisibleRows || !expanded);
        }

        const toggle = toggles.find((btn) => btn.dataset.groupKey === groupKey);
        if (toggle) updateToggleIcon(toggle, expanded);
    }

    function setAllExpanded(expanded) {
        headers.forEach((header) => {
            setGroupExpanded(header.dataset.groupKey, expanded);
        });
    }

    function applySearch() {
        const needle = (searchInput?.value || '').trim().toLowerCase();

        rows.forEach((row) => {
            const hay = (row.dataset.rowText || row.textContent || '').toLowerCase();
            const show = needle === '' || hay.includes(needle);
            row.dataset.searchHidden = show ? 'false' : 'true';
            row.classList.toggle('d-none', !show);
        });

        headers.forEach((header) => {
            const groupKey = header.dataset.groupKey;
            const headerName = (header.dataset.groupName || '').toLowerCase();
            const groupMatches = needle !== '' && headerName.includes(needle);

            if (groupMatches && needle !== '') {
                groupRows(groupKey).forEach((row) => {
                    row.dataset.searchHidden = 'false';
                    row.classList.remove('d-none');
                });
            }

            const finalMatchedRows = groupRows(groupKey).filter((row) => row.dataset.searchHidden !== 'true');
            const hasMatches = finalMatchedRows.length > 0;

            header.classList.toggle('d-none', !hasMatches);
            const subtotalRow = groupSubtotal(groupKey);
            if (subtotalRow) subtotalRow.classList.toggle('d-none', !hasMatches);

            const toggle = toggles.find((btn) => btn.dataset.groupKey === groupKey);
            if (toggle) {
                const expanded = needle !== '' ? true : (toggle.dataset.expanded !== 'false');
                updateToggleIcon(toggle, expanded);
                finalMatchedRows.forEach((row) => row.classList.toggle('d-none', !expanded));
                if (subtotalRow) subtotalRow.classList.toggle('d-none', !hasMatches || !expanded);
            }
        });

        const finalVisibleRows = rows.filter((row) => !row.classList.contains('d-none')).length;
        if (noMatchRow) noMatchRow.classList.toggle('d-none', needle === '' || finalVisibleRows > 0);
    }

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', function () {
            const expanded = this.dataset.expanded !== 'true';
            setGroupExpanded(this.dataset.groupKey, expanded);
        });
    });

    if (expandAllBtn) expandAllBtn.addEventListener('click', () => setAllExpanded(true));
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', () => setAllExpanded(false));

    if (searchInput) searchInput.addEventListener('input', applySearch);
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            rows.forEach((row) => {
                row.dataset.searchHidden = 'false';
                row.classList.remove('d-none');
            });
            headers.forEach((header) => header.classList.remove('d-none'));
            subtotals.forEach((row) => row.classList.remove('d-none'));
            if (noMatchRow) noMatchRow.classList.add('d-none');
            setAllExpanded(true);
        });
    }

    setAllExpanded(true);
    applySearch();
});
</script>
@endpush
