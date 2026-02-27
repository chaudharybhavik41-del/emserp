@extends('layouts.erp')

@section('title', 'GST Sales SAC/HSN Summary')

@section('content')
@php
    $periodFrom = request('from_date', optional($fromDate)->toDateString());
    $periodTo = request('to_date', optional($toDate)->toDateString());
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">GST Sales SAC/HSN Summary</h1>
            <div class="small text-muted">SAC/HSN-wise sales tax summary · {{ $periodFrom }} to {{ $periodTo }}</div>
        </div>
    </div>

    @if(!empty($missingTables))
        <div class="alert alert-warning py-2">
            <strong>Not available:</strong>
            Missing required tables: {{ implode(', ', $missingTables) }}.
            <div class="small mt-1">Please run pending migrations for Client RA Bills module before using this report.</div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Taxable</div>
            <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['taxable'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">GST Total</div>
            <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['gst_total'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Gross Total</div>
            <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['gross_total'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Rows</div>
            <div class="h6 mb-0">{{ $totals['rows'] ?? 0 }}</div>
        </div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="company_id" value="{{ $companyId }}"/>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">From</label>
                    <input type="date" name="from_date" value="{{ $periodFrom }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">To</label>
                    <input type="date" name="to_date" value="{{ $periodTo }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ (string) $projectId === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        @php $s = request('status', $status ?? 'posted'); @endphp
                        <option value="posted" {{ $s === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="approved" {{ $s === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="submitted" {{ $s === 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="draft" {{ $s === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="all" {{ $s === 'all' ? 'selected' : '' }}>All</option>
                    </select>
                </div>

                <div class="col-md-1 d-grid">
                    <button class="btn btn-sm btn-primary">Apply</button>
                </div>

                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.reports.gst-hsn-sales-summary.export', request()->all()) }}">Export CSV</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.reports.gst-hsn-sales-summary') }}">Reset</a>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="ghsSearch" class="form-control" placeholder="HSN/SAC / rate...">
                        <button type="button" id="ghsSearchClear" class="btn btn-outline-secondary">Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">

                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                            <tr>
                                <th class="text-muted" style="width: 180px;">Taxable Value</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['taxable'] ?? 0) }}</td>

                                <th class="text-muted" style="width: 120px;">CGST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['cgst'] ?? 0) }}</td>

                                <th class="text-muted" style="width: 120px;">SGST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['sgst'] ?? 0) }}</td>

                                <th class="text-muted" style="width: 120px;">IGST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['igst'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Total GST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['gst_total'] ?? 0) }}</td>

                                <th class="text-muted">Gross Total</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['gross_total'] ?? 0) }}</td>

                                <th class="text-muted">Rows</th>
                                <td class="text-end" colspan="5">{{ $totals['rows'] ?? 0 }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>SAC/HSN</th>
                                <th class="text-end">GST Rate (%)</th>
                                <th class="text-end">Taxable</th>
                                <th class="text-end">CGST</th>
                                <th class="text-end">SGST</th>
                                <th class="text-end">IGST</th>
                                <th class="text-end">GST Total</th>
                                <th class="text-end">Gross Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr id="ghsNoMatch" class="d-none">
                                <td colspan="8" class="text-center text-muted py-4">No rows match the search text.</td>
                            </tr>
                            @php $hasRows = isset($rows) && method_exists($rows, 'count') && $rows->count() > 0; @endphp

                            @if($hasRows)
                                @foreach($rows as $r)
                                    @php
                                        $taxablePaise = \\App\\Support\\MoneyHelper::toPaise($r->taxable ?? 0);
                                        $cgstPaise    = \\App\\Support\\MoneyHelper::toPaise($r->cgst ?? 0);
                                        $sgstPaise    = \\App\\Support\\MoneyHelper::toPaise($r->sgst ?? 0);
                                        $igstPaise    = \\App\\Support\\MoneyHelper::toPaise($r->igst ?? 0);
                                        $gstPaise     = $cgstPaise + $sgstPaise + $igstPaise;
                                        $grossPaise   = $taxablePaise + $gstPaise;
                                        $searchText = strtolower(trim(($r->hsn_sac ?? '') . ' ' . ($r->gst_rate ?? '')));
                                    @endphp
                                    <tr class="ghs-row" data-row-text="{{ $searchText }}">
                                        <td>{{ $r->hsn_sac }}</td>
                                        <td class="text-end">{{ $r->gst_rate }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($taxablePaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($cgstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($sgstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($igstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($gstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($grossPaise) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No sales lines found for selected filters.</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        Notes:
                        <br>• This summary allocates bill-level GST totals proportionally to line <code>current_amount</code>.
                        <br>• Ensure you maintain <code>sac_hsn_code</code> on Client RA Bill lines for accurate grouping.
                    </p>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.ghs-row:hover td { background: #f5faff; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('ghsSearch');
    const clearBtn = document.getElementById('ghsSearchClear');
    const rows = Array.from(document.querySelectorAll('.ghs-row'));
    const noMatch = document.getElementById('ghsNoMatch');
    if (!input || !rows.length) return;

    const applyFilter = function () {
        const needle = (input.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const hay = (row.dataset.rowText || row.textContent || '').toLowerCase();
            const show = needle === '' || hay.includes(needle);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (noMatch) noMatch.classList.toggle('d-none', needle === '' || visible > 0);
    };

    input.addEventListener('input', applyFilter);
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter();
        });
    }
    applyFilter();
});
</script>
@endpush
