@extends('layouts.erp')

@section('title', 'Ledger')

@section('content')
@php
    $periodFrom = optional($fromDate)->toDateString();
    $periodTo = optional($toDate)->toDateString();
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Ledger Statement</h1>
            <div class="small text-muted">{{ $periodFrom }} to {{ $periodTo }} · Company #{{ $companyId }}</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date" name="from_date" value="{{ request('from_date', $periodFrom) }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date" name="to_date" value="{{ request('to_date', $periodTo) }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Project (optional)</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects (Company Ledger) --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected((string) $projectId === (string) $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small">If a project is selected, opening balance is calculated from <strong>project-tagged movements</strong> only.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Account</label>
                    <select name="account_id" class="form-select form-select-sm">
                        @foreach($accounts as $a)
                            <option value="{{ $a->id }}" @selected(optional($account)->id === $a->id)>
                                {{ $a->code }} - {{ $a->name }}@if(!$a->is_active) (Inactive)@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-7 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter"></i> Apply</button>
                    <a href="{{ route('accounting.reports.ledger') }}" class="btn btn-outline-secondary btn-sm">Reset</a>

                    @if($account)
                        <a href="{{ route('accounting.reports.ledger', array_merge(request()->all(), ['export' => 'csv'])) }}"
                           class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
                    @endif

                    <div class="form-check form-check-inline ms-2 mt-1">
                        <input class="form-check-input" type="checkbox" id="show_breakdown" name="show_breakdown" value="1" @checked(request()->boolean('show_breakdown'))>
                        <label class="form-check-label small" for="show_breakdown">Show voucher break-up (TDS / GST / Retention etc.)</label>
                    </div>
                </div>

                <div class="col-md-5">
                    <label class="form-label form-label-sm">Quick search entries</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="ledgerSearch" class="form-control" placeholder="Date/voucher/type/description/ref..." {{ !$account ? 'disabled' : '' }}>
                        <button type="button" id="ledgerSearchClear" class="btn btn-outline-secondary" {{ !$account ? 'disabled' : '' }}>Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(!$account)
        <div class="alert alert-info">No accounts found.</div>
    @else
        @php
            $openingType = $openingBalance >= 0 ? 'Dr' : 'Cr';
            $closingType = $closingBalance >= 0 ? 'Dr' : 'Cr';
        @endphp

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Ledger</div>
                            <div class="fw-semibold">{{ $account->name }} <span class="text-muted">({{ $account->code }})</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Opening Balance</div>
                            <div class="fw-semibold">{{ number_format(abs($openingBalance), 2) }} {{ $openingType }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Closing Balance</div>
                            <div class="fw-semibold">{{ number_format(abs($closingBalance), 2) }} {{ $closingType }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(($showBreakdown ?? false) && count($ledgerEntries))
            <div class="mb-2 d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="ledgerExpandBreakdown">Expand all break-ups</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="ledgerCollapseBreakdown">Collapse all break-ups</button>
            </div>
        @endif

        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div class="fw-semibold small">Period: {{ $periodFrom }} to {{ $periodTo }}</div>
                <div class="small text-muted">
                    @if($projectId)
                        Project filter applied
                    @endif
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%">Date</th>
                            <th style="width: 12%">Voucher No</th>
                            <th style="width: 10%">Type</th>
                            <th>Description</th>
                            <th style="width: 10%" class="text-end">Debit</th>
                            <th style="width: 10%" class="text-end">Credit</th>
                            <th style="width: 12%" class="text-end">Running</th>
                            <th style="width: 6%" class="text-center">Dr/Cr</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $running = $openingBalance; @endphp

                        <tr class="table-light">
                            <td colspan="4" class="small fw-semibold">Opening Balance</td>
                            <td class="text-end small">&nbsp;</td>
                            <td class="text-end small">&nbsp;</td>
                            <td class="text-end small fw-semibold">{{ number_format(abs($running), 2) }}</td>
                            <td class="text-center small fw-semibold">{{ $running >= 0 ? 'Dr' : 'Cr' }}</td>
                        </tr>

                        <tr id="ledgerNoMatch" class="d-none">
                            <td colspan="8" class="text-center small text-muted py-3">No ledger entries match the search text.</td>
                        </tr>

                        @if(count($ledgerEntries))
                            @foreach($ledgerEntries as $entry)
                                @php
                                    $running += ((float) $entry->debit - (float) $entry->credit);
                                    $searchText = strtolower(trim(
                                        (optional($entry->voucher->voucher_date)->toDateString() ?? '') . ' ' .
                                        ($entry->voucher->voucher_no ?? '') . ' ' .
                                        ($entry->voucher->voucher_type ?? '') . ' ' .
                                        ($entry->description ?: ($entry->voucher->narration ?: '-')) . ' ' .
                                        ($entry->voucher->reference ?? '')
                                    ));
                                @endphp
                                <tr class="ledger-entry-row" data-voucher-id="{{ $entry->voucher_id }}" data-row-text="{{ $searchText }}">
                                    <td class="small">{{ optional($entry->voucher->voucher_date)->toDateString() }}</td>
                                    <td class="small">
                                        <a href="{{ route('accounting.vouchers.show', $entry->voucher) }}" class="text-decoration-none">{{ $entry->voucher->voucher_no }}</a>
                                    </td>
                                    <td class="small text-uppercase">{{ $entry->voucher->voucher_type }}</td>
                                    <td class="small">
                                        <div class="fw-semibold">{{ $entry->description ?: ($entry->voucher->narration ?: '-') }}</div>
                                        @if($entry->costCenter)
                                            <div class="text-muted">Cost Center: {{ $entry->costCenter->name }}</div>
                                        @endif
                                        @if($entry->voucher->reference)
                                            <div class="text-muted">Ref: {{ $entry->voucher->reference }}</div>
                                        @endif
                                    </td>
                                    <td class="small text-end">{{ number_format($entry->debit, 2) }}</td>
                                    <td class="small text-end">{{ number_format($entry->credit, 2) }}</td>
                                    <td class="small text-end fw-semibold">{{ number_format(abs($running), 2) }}</td>
                                    <td class="small text-center fw-semibold">{{ $running >= 0 ? 'Dr' : 'Cr' }}</td>
                                </tr>

                                @if(($showBreakdown ?? false) && isset($voucherLinesByVoucher))
                                    @php $vLines = $voucherLinesByVoucher->get($entry->voucher_id, collect()); @endphp

                                    @if($vLines->count() > 1)
                                        <tr class="table-light ledger-breakdown-row" data-voucher-id="{{ $entry->voucher_id }}" data-expanded="true">
                                            <td colspan="8" class="p-2">
                                                <div class="small text-muted mb-1">Voucher break-up</div>
                                                <table class="table table-sm table-bordered mb-0 align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="small">Account</th>
                                                            <th class="small">Line Description</th>
                                                            <th class="small text-end" style="width: 12%">Debit</th>
                                                            <th class="small text-end" style="width: 12%">Credit</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($vLines as $vl)
                                                            <tr @class(['table-primary' => (int) $vl->id === (int) $entry->id])>
                                                                <td class="small">{{ $vl->account?->code }} - {{ $vl->account?->name }}</td>
                                                                <td class="small">{{ $vl->description }}</td>
                                                                <td class="small text-end">{{ number_format($vl->debit, 2) }}</td>
                                                                <td class="small text-end">{{ number_format($vl->credit, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach
                        @else
                            <tr>
                                <td colspan="8" class="text-center small text-muted py-2">No ledger entries for the selected filters.</td>
                            </tr>
                        @endif

                        <tr class="table-dark text-white fw-semibold">
                            <td colspan="6" class="text-end small">Closing Balance</td>
                            <td class="text-end small">{{ number_format(abs($closingBalance), 2) }}</td>
                            <td class="text-center small">{{ $closingBalance >= 0 ? 'Dr' : 'Cr' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.ledger-entry-row:hover td { background: #f6faff; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('ledgerSearch');
    const clearBtn = document.getElementById('ledgerSearchClear');
    const noMatch = document.getElementById('ledgerNoMatch');
    const entryRows = Array.from(document.querySelectorAll('.ledger-entry-row'));
    const breakdownRows = Array.from(document.querySelectorAll('.ledger-breakdown-row'));
    const expandBtn = document.getElementById('ledgerExpandBreakdown');
    const collapseBtn = document.getElementById('ledgerCollapseBreakdown');

    const setAllBreakdown = function (expanded) {
        breakdownRows.forEach((row) => {
            row.dataset.expanded = expanded ? 'true' : 'false';
            row.classList.toggle('d-none', !expanded);
        });
    };

    if (expandBtn) expandBtn.addEventListener('click', () => setAllBreakdown(true));
    if (collapseBtn) collapseBtn.addEventListener('click', () => setAllBreakdown(false));

    if (searchInput && entryRows.length) {
        const applyFilter = function () {
            const needle = (searchInput.value || '').trim().toLowerCase();
            let visible = 0;

            entryRows.forEach((row) => {
                const hay = (row.dataset.rowText || row.textContent || '').toLowerCase();
                const show = needle === '' || hay.includes(needle);
                row.classList.toggle('d-none', !show);
                if (show) visible++;

                const voucherId = row.dataset.voucherId;
                breakdownRows
                    .filter((b) => b.dataset.voucherId === voucherId)
                    .forEach((b) => {
                        if (!show) {
                            b.classList.add('d-none');
                        } else {
                            const expanded = b.dataset.expanded !== 'false';
                            b.classList.toggle('d-none', !expanded);
                        }
                    });
            });

            if (noMatch) noMatch.classList.toggle('d-none', needle === '' || visible > 0);
        };

        searchInput.addEventListener('input', applyFilter);
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                applyFilter();
            });
        }
        applyFilter();
    }
});
</script>
@endpush
