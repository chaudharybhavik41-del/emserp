@extends('layouts.erp')

@section('title', 'Inventory Valuation')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Inventory Valuation &amp; Reconciliation</h1>

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
                    <label class="form-label form-label-sm">Project (optional)</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected((string) $projectId === (string) $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small">
                        If a project is selected, ledger balances are computed using <strong>only vouchers tagged to that project</strong> (opening balances are company-level and excluded).
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label form-label-sm">Item (optional)</label>
                    <select name="item_id" class="form-select form-select-sm">
                        <option value="">-- All Items --</option>
                        @foreach($items as $it)
                            <option value="{{ $it->id }}" @selected((string) $itemId === (string) $it->id)>
                                {{ $it->code ? $it->code.' - ' : '' }}{{ $it->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-1">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="details" name="details" {{ $details ? 'checked' : '' }}>
                        <label class="form-check-label small" for="details">
                            Details
                        </label>
                    </div>
                </div>

                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter"></i> Apply
                    </button>

                    <a href="{{ route('accounting.reports.inventory-valuation') }}"
                       class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>

                    <a href="{{ route('accounting.reports.inventory-valuation', array_merge(request()->all(), ['export' => 'csv'])) }}"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-info py-2">
        <div class="small">
            <div class="fw-semibold mb-1">How this report works</div>
            <ul class="mb-0">
                <li><strong>Stock snapshot</strong> is taken from <code>store_stock_items</code> current availability (live quantities).</li>
                <li><strong>Ledger balance</strong> is computed from <strong>posted vouchers</strong> up to the selected “As on date”.</li>
                <li><strong>Client-supplied material</strong> is treated as <strong>quantity-only</strong> (value = 0).</li>
                <li><strong>Unvalued lines</strong> usually mean GRN exists but no posted Purchase Bill (or no PO rate). These will show stock value = 0 and create a difference.</li>
                @if($fallbackInventoryAccount)
                    <li>Items without an inventory account are mapped to fallback inventory account: <strong>{{ $fallbackInventoryAccount->code }} - {{ $fallbackInventoryAccount->name }}</strong>.</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search summary rows</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="ivSummarySearch" class="form-control"
                               placeholder="Filter by account code/name...">
                        <button type="button" id="ivSummarySearchClear" class="btn btn-outline-secondary">Clear</button>
                    </div>
                </div>
                @if($details)
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Quick search detail lines</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="ivDetailSearch" class="form-control"
                                   placeholder="Filter item/project/category/cost source...">
                            <button type="button" id="ivDetailSearchClear" class="btn btn-outline-secondary">Clear</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">
                Summary as on {{ optional($asOfDate)->toDateString() }}
                @if($projectId)
                    <span class="text-muted">(Project filtered)</span>
                @endif
            </div>
            <div class="small text-muted">
                Posted vouchers only
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 12%;">Account Code</th>
                            <th style="width: 30%;">Account Name</th>
                            <th style="width: 14%;" class="text-end">Ledger Balance</th>
                            <th style="width: 14%;" class="text-end">Stock Value</th>
                            <th style="width: 14%;" class="text-end">Difference</th>
                            <th style="width: 6%;" class="text-end">Lines</th>
                            <th style="width: 6%;" class="text-end">Unvalued</th>
                            <th style="width: 6%;" class="text-end">Client</th>
                        </tr>
                    </thead>
                    @php
                        $ledgerTo = optional($asOfDate)->toDateString();
                        $ledgerFrom = optional($asOfDate)->copy()->startOfMonth()->toDateString();
                    @endphp
                    <tbody>
                        <tr id="ivSummaryNoMatch" class="d-none">
                            <td colspan="8" class="text-center small text-muted py-3">No summary rows match the search text.</td>
                        </tr>
                        @if(count($summaryRows))
                            @foreach($summaryRows as $row)
                                @php
                                    $acc = $row['account'];
                                    $diff = (float) $row['difference'];
                                    $summarySearchText = strtolower(trim(
                                        ($acc?->code ?? '') . ' ' .
                                        ($acc?->name ?? '') . ' ' .
                                        (($acc ? '' : 'unknown unmapped inventory account'))
                                    ));
                                @endphp
                                <tr class="iv-summary-row" data-summary-text="{{ $summarySearchText }}">
                                    <td class="small">{{ $acc?->code ?? 'N/A' }}</td>
                                    <td class="small">
                                        @if($acc)
                                            <a href="{{ route('accounting.reports.ledger', array_filter([
                                                'account_id' => $acc->id,
                                                'from_date' => $ledgerFrom,
                                                'to_date' => $ledgerTo,
                                                'project_id' => $projectId,
                                            ])) }}" class="text-decoration-none">
                                                {{ $acc->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">Unknown / Unmapped Inventory Account</span>
                                        @endif
                                    </td>
                                    <td class="small text-end">{{ number_format((float) $row['ledger_net'], 2) }}</td>
                                    <td class="small text-end">{{ number_format((float) $row['stock_value'], 2) }}</td>
                                    <td class="small text-end {{ abs($diff) > 0.01 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        {{ number_format($diff, 2) }}
                                    </td>
                                    <td class="small text-end">{{ (int) $row['line_count'] }}</td>
                                    <td class="small text-end {{ (int) $row['unvalued_line_count'] > 0 ? 'text-warning fw-semibold' : '' }}">
                                        {{ (int) $row['unvalued_line_count'] }}
                                    </td>
                                    <td class="small text-end">{{ (int) $row['client_line_count'] }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="8" class="text-center small text-muted py-3">
                                    No stock available for the selected filters.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    @if(count($summaryRows))
                        <tfoot>
                            <tr class="table-dark fw-semibold text-white">
                                <td colspan="2" class="text-end small">Grand Total</td>
                                <td class="small text-end">{{ number_format((float) $grandLedger, 2) }}</td>
                                <td class="small text-end">{{ number_format((float) $grandStock, 2) }}</td>
                                <td class="small text-end">{{ number_format((float) $grandDiff, 2) }}</td>
                                <td class="small text-end"></td>
                                <td class="small text-end"></td>
                                <td class="small text-end"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($details)
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div class="fw-semibold small">Stock Detail (live)</div>
                <div class="small text-muted">{{ count($detailRows) }} line(s)</div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 6%;">Stock ID</th>
                                <th style="width: 22%;">Item</th>
                                <th style="width: 16%;">Project</th>
                                <th style="width: 10%;">Mat. Category</th>
                                <th style="width: 8%;">Grade</th>
                                <th style="width: 10%;" class="text-end">Qty</th>
                                <th style="width: 10%;" class="text-end">Unit Rate</th>
                                <th style="width: 10%;" class="text-end">Value</th>
                                <th style="width: 8%;">Cost</th>
                                <th style="width: 10%;">GRN / Ref</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="ivDetailNoMatch" class="d-none">
                                <td colspan="10" class="text-center small text-muted py-3">No detail rows match the search text.</td>
                            </tr>
                            @if(count($detailRows))
                                @foreach($detailRows as $d)
                                    @php
                                        $item = $d['item'];
                                        $project = $d['project'];
                                        $isClient = (bool) ($d['is_client_material'] ?? false);
                                        $cost = (string) ($d['cost_source'] ?? '');
                                        $detailSearchText = strtolower(trim(
                                            ($item?->code ?? '') . ' ' .
                                            ($item?->name ?? '') . ' ' .
                                            ($project?->code ?? '') . ' ' .
                                            ($project?->name ?? '') . ' ' .
                                            ($d['material_category'] ?? '') . ' ' .
                                            ($d['grade'] ?? '') . ' ' .
                                            ($d['qty_uom'] ?? '') . ' ' .
                                            ($d['source_reference'] ?? '') . ' ' .
                                            ($cost ?? '')
                                        ));
                                    @endphp
                                    <tr class="iv-detail-row" data-detail-text="{{ $detailSearchText }}">
                                        <td class="small">{{ $d['stock_id'] }}</td>
                                        <td class="small">
                                            {{ $item?->code ? $item->code.' - ' : '' }}{{ $item?->name }}
                                            @if($d['category'])
                                                <div class="text-muted small">{{ $d['category']->code ?? '' }} {{ $d['category']->name ?? '' }}</div>
                                            @endif
                                        </td>
                                        <td class="small">
                                            {{ $project?->code ? $project->code.' - ' : '' }}{{ $project?->name }}
                                        </td>
                                        <td class="small">{{ $d['material_category'] }}</td>
                                        <td class="small">{{ $d['grade'] }}</td>
                                        <td class="small text-end">
                                            {{ number_format((float) $d['qty'], 3) }} <span class="text-muted">{{ $d['qty_uom'] }}</span>
                                        </td>
                                        <td class="small text-end">{{ number_format((float) $d['unit_rate'], 4) }}</td>
                                        <td class="small text-end">{{ number_format((float) $d['value'], 2) }}</td>
                                        <td class="small">
                                            @if($isClient)
                                                <span class="badge bg-secondary">Client</span>
                                            @elseif($cost === 'invoice')
                                                <span class="badge bg-success">Invoice</span>
                                            @elseif($cost === 'po')
                                                <span class="badge bg-info text-dark">PO</span>
                                            @elseif(str_starts_with($cost, 'source:'))
                                                <span class="badge bg-warning text-dark">{{ $cost }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Unvalued</span>
                                            @endif
                                        </td>
                                        <td class="small">
                                            @if($d['receipt_no'])
                                                <div><strong>{{ $d['receipt_no'] }}</strong></div>
                                            @endif
                                            @if($d['source_reference'])
                                                <div class="text-muted">{{ $d['source_reference'] }}</div>
                                            @endif
                                            @if($d['mr_line_id'])
                                                <div class="text-muted small">MRL #{{ $d['mr_line_id'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="10" class="text-center small text-muted py-3">
                                        No detail lines.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
.iv-summary-row:hover td,
.iv-detail-row:hover td {
    background: #f7faff;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function initSearch(inputId, clearId, rowSelector, textKey, noMatchId) {
        const input = document.getElementById(inputId);
        const clearBtn = document.getElementById(clearId);
        const noMatchRow = document.getElementById(noMatchId);
        const rows = Array.from(document.querySelectorAll(rowSelector));
        if (!input || !rows.length) return;

        const applyFilter = function () {
            const needle = (input.value || '').trim().toLowerCase();
            let visible = 0;
            rows.forEach((row) => {
                const haystack = (row.dataset[textKey] || row.textContent || '').toLowerCase();
                const show = needle === '' || haystack.includes(needle);
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            if (noMatchRow) noMatchRow.classList.toggle('d-none', needle === '' || visible > 0);
        };

        input.addEventListener('input', applyFilter);
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                applyFilter();
            });
        }

        applyFilter();
    }

    initSearch('ivSummarySearch', 'ivSummarySearchClear', '.iv-summary-row', 'summaryText', 'ivSummaryNoMatch');
    initSearch('ivDetailSearch', 'ivDetailSearchClear', '.iv-detail-row', 'detailText', 'ivDetailNoMatch');
});
</script>
@endpush
