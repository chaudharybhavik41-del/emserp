@extends('layouts.erp')

@section('title', 'GST Purchase Register')

@section('content')
@php
    $periodFrom = request('from_date', optional($fromDate)->toDateString());
    $periodTo = request('to_date', optional($toDate)->toDateString());
    $gstTotalPaise = (int) (($totals['cgst'] ?? 0) + ($totals['sgst'] ?? 0) + ($totals['igst'] ?? 0));
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">GST Purchase Register (Invoice-wise)</h1>
            <div class="small text-muted">Posting-date based · {{ $periodFrom }} to {{ $periodTo }}</div>
        </div>
    </div>

    <p class="text-muted small mb-3">
        Date filter is applied on <strong>Posting Date</strong> (books/voucher date). Invoice Date is shown separately for GST compliance.
    </p>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Taxable Value</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['taxable'] ?? 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">GST Total</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($gstTotalPaise) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Invoice Total</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['invoice'] ?? 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Net Payable</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['net_payable'] ?? 0) }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="company_id" value="{{ $companyId }}"/>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Posting Date From</label>
                    <input type="date" name="from_date" value="{{ $periodFrom }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Posting Date To</label>
                    <input type="date" name="to_date" value="{{ $periodTo }}" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">All suppliers</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ (string) $supplierId === (string) $s->id ? 'selected' : '' }}>
                                {{ $s->name }}{{ $s->gstin ? ' - ' . $s->gstin : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        @php $curStatus = request('status', $status ?? 'posted'); @endphp
                        <option value="posted" {{ $curStatus === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="draft" {{ $curStatus === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="cancelled" {{ $curStatus === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="all" {{ $curStatus === 'all' ? 'selected' : '' }}>All</option>
                    </select>
                </div>

                <div class="col-md-1 d-grid">
                    <button class="btn btn-sm btn-primary">Apply</button>
                </div>

                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('accounting.reports.gst-purchase-register.export', request()->all()) }}">
                        Export CSV
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.reports.gst-purchase-register') }}">Reset</a>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="gprSearch" class="form-control" placeholder="Invoice no / voucher / supplier / gstin / status...">
                        <button type="button" id="gprSearchClear" class="btn btn-outline-secondary">Clear</button>
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
                                <th class="text-muted">RCM CGST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['rcm_cgst'] ?? 0) }}</td>

                                <th class="text-muted">RCM SGST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['rcm_sgst'] ?? 0) }}</td>

                                <th class="text-muted">RCM IGST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['rcm_igst'] ?? 0) }}</td>

                                @php
                                    $rcmTotalPaise = ($totals['rcm_cgst'] ?? 0) + ($totals['rcm_sgst'] ?? 0) + ($totals['rcm_igst'] ?? 0);
                                @endphp
                                <th class="text-muted">RCM Total GST</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($rcmTotalPaise) }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Invoice Total</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['invoice'] ?? 0) }}</td>

                                <th class="text-muted">TCS</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['tcs_amount'] ?? 0) }}</td>

                                <th class="text-muted">TDS</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['tds_amount'] ?? 0) }}</td>

                                <th class="text-muted">Net Payable</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['net_payable'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Bills</th>
                                <td class="text-end" colspan="7">{{ $bills->count() }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Invoice Date</th>
                                <th>Posting Date</th>
                                <th>Bill No</th>
                                <th>Voucher No</th>
                                <th>Supplier</th>
                                <th>GSTIN</th>
                                <th class="text-end">Taxable</th>
                                <th class="text-end">CGST</th>
                                <th class="text-end">SGST</th>
                                <th class="text-end">IGST</th>
                                <th class="text-end">GST Total</th>
                                <th class="text-end">RCM GST</th>
                                <th class="text-end">Invoice</th>
                                <th class="text-end">TCS</th>
                                <th class="text-end">TDS</th>
                                <th class="text-end">Net Payable</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr id="gprNoMatch" class="d-none">
                                <td colspan="17" class="text-center text-muted py-4">No bills match the search text.</td>
                            </tr>
                            @if(count($bills))
                                @foreach($bills as $bill)
                                    @php
                                        $taxablePaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_basic') ?? $bill->total_basic ?? 0);
                                        $cgstPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_cgst') ?? $bill->total_cgst ?? 0);
                                        $sgstPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_sgst') ?? $bill->total_sgst ?? 0);
                                        $igstPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_igst') ?? $bill->total_igst ?? 0);
                                        $gstPaise     = $cgstPaise + $sgstPaise + $igstPaise;
                                        $rcmCgstPaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_rcm_cgst') ?? $bill->total_rcm_cgst ?? 0);
                                        $rcmSgstPaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_rcm_sgst') ?? $bill->total_rcm_sgst ?? 0);
                                        $rcmIgstPaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_rcm_igst') ?? $bill->total_rcm_igst ?? 0);
                                        $rcmGstPaise  = $rcmCgstPaise + $rcmSgstPaise + $rcmIgstPaise;
                                        $invoicePaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_amount') ?? $bill->total_amount ?? 0);
                                        $tcsPaise     = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('tcs_amount') ?? $bill->tcs_amount ?? 0);
                                        $tdsPaise     = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('tds_amount') ?? $bill->tds_amount ?? 0);
                                        $netPaise     = ($invoicePaise + $tcsPaise) - $tdsPaise;
                                        $postingDate  = optional($bill->getAttribute('posting_date') ?: optional($bill->voucher)->voucher_date ?: $bill->bill_date)->toDateString();
                                        $searchText = strtolower(trim(
                                            (optional($bill->bill_date)->toDateString() ?? '') . ' ' .
                                            ($postingDate ?? '') . ' ' .
                                            ($bill->bill_number ?? '') . ' ' .
                                            (optional($bill->voucher)->voucher_no ?? '') . ' ' .
                                            ($bill->supplier?->name ?? '') . ' ' .
                                            ($bill->supplier?->gstin ?? '') . ' ' .
                                            ($bill->status ?? '')
                                        ));
                                    @endphp
                                    <tr class="gpr-row" data-row-text="{{ $searchText }}">
                                        <td>{{ optional($bill->bill_date)->toDateString() }}</td>
                                        <td>{{ $postingDate }}</td>
                                        <td>{{ $bill->bill_number }}</td>
                                        <td>
                                            @if($bill->voucher)
                                                <a href="{{ route('accounting.vouchers.show', $bill->voucher) }}" class="text-decoration-none">
                                                    {{ $bill->voucher->voucher_no }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($bill->supplier)
                                                <a href="{{ route('accounting.reports.supplier-ageing', ['supplier_id' => $bill->supplier->id, 'as_of_date' => $periodTo]) }}" class="text-decoration-none">
                                                    {{ $bill->supplier->name }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $bill->supplier?->gstin }}</td>

                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($taxablePaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($cgstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($sgstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($igstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($gstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($rcmGstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($invoicePaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($tcsPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($tdsPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($netPaise) }}</td>

                                        <td>
                                            <span class="badge bg-{{ $bill->status === 'posted' ? 'success' : ($bill->status === 'draft' ? 'secondary' : 'danger') }}">
                                                {{ $bill->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="17" class="text-center text-muted py-4">
                                        No purchase bills found for selected filters.
                                    </td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        Notes:
                        <br>• This register uses the GST split stored on Purchase Bills (CGST/SGST/IGST totals).
                        <br>• If you post manual GST vouchers (without Purchase Bills), they will not appear here (we can add a voucher-based register in a later phase if required).
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.gpr-row:hover td { background: #f5faff; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('gprSearch');
    const clearBtn = document.getElementById('gprSearchClear');
    const rows = Array.from(document.querySelectorAll('.gpr-row'));
    const noMatch = document.getElementById('gprNoMatch');
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
