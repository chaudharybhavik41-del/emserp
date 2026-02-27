@extends('layouts.erp')

@section('title', 'GST Sales Register')

@section('content')
@php
    $periodFrom = request('from_date', optional($fromDate)->toDateString());
    $periodTo = request('to_date', optional($toDate)->toDateString());
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">GST Sales Register (Invoice-wise)</h1>
            <div class="small text-muted">{{ $periodFrom }} to {{ $periodTo }} · Client RA/Sales invoices</div>
        </div>
    </div>

    @if(($missingTable ?? false) === true)
        <div class="alert alert-warning">
            Sales/RA tables are not available in this database (<code>client_ra_bills</code> missing).
            Please run migrations for the Client RA Bill module to enable this report.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Taxable Value</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['taxable'] ?? 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Total GST</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['total_gst'] ?? 0) }}</div>
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
                <div class="text-muted small">Receivable</div>
                <div class="h6 mb-0">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['receivable'] ?? 0) }}</div>
            </div></div>
        </div>
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
                    <label class="form-label form-label-sm">Client</label>
                    <select name="client_id" class="form-select form-select-sm">
                        <option value="">All clients</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ (string) $clientId === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}{{ $c->gstin ? ' - ' . $c->gstin : '' }}
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
                    <button class="btn btn-sm btn-primary" {{ ($missingTable ?? false) ? 'disabled' : '' }}>Apply</button>
                </div>

                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary {{ ($missingTable ?? false) ? 'disabled' : '' }}"
                       href="{{ ($missingTable ?? false) ? '#' : route('accounting.reports.gst-sales-register.export', request()->all()) }}">
                        Export CSV
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.reports.gst-sales-register') }}">Reset</a>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="gsrSearch" class="form-control" placeholder="Invoice / RA / voucher / client / project..." {{ ($missingTable ?? false) ? 'disabled' : '' }}>
                        <button type="button" id="gsrSearchClear" class="btn btn-outline-secondary" {{ ($missingTable ?? false) ? 'disabled' : '' }}>Clear</button>
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
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['total_gst'] ?? 0) }}</td>

                                <th class="text-muted">Invoice Total</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['invoice'] ?? 0) }}</td>

                                <th class="text-muted">TDS</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['tds_amount'] ?? 0) }}</td>

                                <th class="text-muted">Receivable</th>
                                <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($totals['receivable'] ?? 0) }}</td>
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
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>RA No</th>
                                <th>Voucher No</th>
                                <th>Client</th>
                                <th>GSTIN</th>
                                <th>Project</th>
                                <th class="text-end">Taxable</th>
                                <th class="text-end">CGST</th>
                                <th class="text-end">SGST</th>
                                <th class="text-end">IGST</th>
                                <th class="text-end">GST Total</th>
                                <th class="text-end">Invoice</th>
                                <th class="text-end">TDS</th>
                                <th class="text-end">Receivable</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr id="gsrNoMatch" class="d-none">
                                <td colspan="16" class="text-center text-muted py-4">No sales bills match the search text.</td>
                            </tr>
                            @if(count($bills))
                                @foreach($bills as $bill)
                                    @php
                                        $taxablePaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('net_amount') ?? $bill->net_amount ?? 0);
                                        $cgstPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('cgst_amount') ?? $bill->cgst_amount ?? 0);
                                        $sgstPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('sgst_amount') ?? $bill->sgst_amount ?? 0);
                                        $igstPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('igst_amount') ?? $bill->igst_amount ?? 0);
                                        $gstPaise     = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_gst') ?? $bill->total_gst ?? 0);
                                        $invoicePaise = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('total_amount') ?? $bill->total_amount ?? 0);
                                        $tdsPaise     = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('tds_amount') ?? $bill->tds_amount ?? 0);
                                        $recvPaise    = \\App\\Support\\MoneyHelper::toPaise($bill->getRawOriginal('receivable_amount') ?? $bill->receivable_amount ?? 0);
                                        $searchText = strtolower(trim(
                                            (optional($bill->bill_date)->toDateString() ?? '') . ' ' .
                                            ($bill->invoice_number ?? '') . ' ' .
                                            ($bill->ra_number ?? '') . ' ' .
                                            (optional($bill->voucher)->voucher_no ?? '') . ' ' .
                                            ($bill->client?->name ?? '') . ' ' .
                                            ($bill->client?->gstin ?? '') . ' ' .
                                            ($bill->project?->name ?? '') . ' ' .
                                            ($bill->status ?? '')
                                        ));
                                    @endphp
                                    <tr class="gsr-row" data-row-text="{{ $searchText }}">
                                        <td>{{ optional($bill->bill_date)->toDateString() }}</td>
                                        <td>{{ $bill->invoice_number }}</td>
                                        <td>{{ $bill->ra_number }}</td>
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
                                            @if($bill->client)
                                                <a href="{{ route('accounting.reports.client-ageing', ['client_id' => $bill->client->id, 'as_of_date' => $periodTo]) }}" class="text-decoration-none">
                                                    {{ $bill->client->name }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $bill->client?->gstin }}</td>
                                        <td>{{ $bill->project?->name }}</td>

                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($taxablePaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($cgstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($sgstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($igstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($gstPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($invoicePaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($tdsPaise) }}</td>
                                        <td class="text-end">{{ \\App\\Support\\MoneyHelper::fromPaise($recvPaise) }}</td>

                                        <td>
                                            <span class="badge bg-{{ $bill->status === 'posted' ? 'success' : ($bill->status === 'draft' ? 'secondary' : 'danger') }}">
                                                {{ $bill->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="16" class="text-center text-muted py-4">
                                        No sales bills found for selected filters.
                                    </td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        Notes:
                        <br>• This register uses Client RA Bills / Sales Invoices module totals (net, GST split, and receivable).
                        <br>• If you generate sales vouchers without Client RA Bills, they will not appear here (we can add a voucher-based sales register in a later phase if required).
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.gsr-row:hover td { background: #f5faff; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('gsrSearch');
    const clearBtn = document.getElementById('gsrSearchClear');
    const rows = Array.from(document.querySelectorAll('.gsr-row'));
    const noMatch = document.getElementById('gsrNoMatch');
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
