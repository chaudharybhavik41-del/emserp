@extends('layouts.erp')

@section('title', 'Receipt Voucher')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Receipt Voucher</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.receipts.store') }}" data-prevent-enter-submit="1">
                @csrf

                <input type="hidden" name="company_id" value="{{ $companyId }}">

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Voucher No</label>
                        <input type="text"
                               class="form-control form-control-sm"
                               value="Auto-generated on Save"
                               disabled>
                        <div class="form-text">Voucher number is generated automatically from the central voucher series.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Date</label>
                        <input type="date" name="voucher_date" id="voucher_date"
                               class="form-control form-control-sm @error('voucher_date') is-invalid @enderror"
                               value="{{ old('voucher_date', now()->toDateString()) }}">
                        @error('voucher_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Project (optional)</label>
                        <select name="project_id"
                                class="form-select form-select-sm @error('project_id') is-invalid @enderror">
                            <option value="">-- None --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}"
                                    @selected(old('project_id') == $project->id)>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Cost Center (optional)</label>
                        <select name="cost_center_id"
                                class="form-select form-select-sm @error('cost_center_id') is-invalid @enderror">
                            <option value="">-- None --</option>
                            @foreach($costCenters as $cc)
                                <option value="{{ $cc->id }}"
                                    @selected(old('cost_center_id') == $cc->id)>
                                    {{ $cc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('cost_center_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Bank / Cash Account</label>
                        <select name="bank_account_id"
                                class="form-select form-select-sm @error('bank_account_id') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($bankCashAccounts as $acc)
                                <option value="{{ $acc->id }}"
                                    @selected(old('bank_account_id') == $acc->id)>
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('bank_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label form-label-sm">From Ledger (Client / Debtor)</label>
                        <select name="party_account_id" id="party_account_id"
                                class="form-select form-select-sm @error('party_account_id') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($counterpartyAccounts as $acc)
                                <option value="{{ $acc->id }}"
                                    @selected(old('party_account_id') == $acc->id)>
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('party_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Amount</label>
                        <input type="number" step="0.01" name="amount" id="voucher_amount"
                               class="form-control form-control-sm @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}">
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Reference (optional)</label>
                        <input type="text" name="reference"
                               class="form-control form-control-sm @error('reference') is-invalid @enderror"
                               value="{{ old('reference') }}">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Narration</label>
                        <textarea name="narration" rows="2"
                                  class="form-control form-control-sm @error('narration') is-invalid @enderror">{{ old('narration') }}</textarea>
                        @error('narration')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr class="mt-3 mb-2">

                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-semibold small">Bill allocations (Client RA / Invoices)</span>
                        <span class="text-muted small ms-2">(optional)</span>
                    </div>
                    <div class="small text-muted">
                        This will become active once Client RA / Invoice module is wired to accounts.
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 18%;">Bill No</th>
                                <th style="width: 18%;">Bill Date</th>
                                <th style="width: 18%;">Bill Amount</th>
                                <th style="width: 18%;">Outstanding</th>
                                <th style="width: 28%;">Allocate Amount</th>
                            </tr>
                        </thead>
                        <tbody id="client-bill-rows">
                            <tr class="text-muted">
                                <td colspan="5" class="text-center small py-2">
                                    Select a client/debtor ledger to load open bills for allocation.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @error('receipt_allocations.*.amount')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror
                @error('receipt_allocations.*.bill_id')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror

                <div class="small text-muted mb-3">
                    You do not have to allocate the full amount. Any unallocated balance will be treated as on-account receipt.
                </div>

                <div class="border rounded p-3 bg-light mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="small fw-semibold">TDS Certificate Tracking (Client deduction)</div>
                        <div class="small text-muted">Expected TDS is auto-calculated from allocated bills</div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">TDS Section</label>
                            <select name="tds_section" id="tds_section" class="form-select form-select-sm">
                                <option value="">-- Select --</option>
                                @foreach(($tdsSections ?? []) as $sec)
                                    <option value="{{ $sec->code }}"
                                            data-rate="{{ number_format((float) $sec->default_rate, 4, '.', '') }}"
                                            {{ old('tds_section') == $sec->code ? 'selected' : '' }}>
                                        {{ $sec->code }} - {{ $sec->name }} ({{ number_format((float) $sec->default_rate, 4) }}%)
                                    </option>
                                @endforeach
                            </select>
                            @error('tds_section')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Rate %</label>
                            <input type="number" step="0.0001" min="0" max="100"
                                   name="tds_rate" id="tds_rate"
                                   class="form-control form-control-sm"
                                   value="{{ old('tds_rate') }}">
                            @error('tds_rate')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Expected TDS</label>
                            <input type="text" readonly id="expected_tds_amount"
                                   class="form-control form-control-sm bg-white"
                                   value="0.00">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small mb-1">Certificate No</label>
                            <input type="text" name="tds_certificate_no" maxlength="100"
                                   class="form-control form-control-sm"
                                   value="{{ old('tds_certificate_no') }}">
                            @error('tds_certificate_no')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small mb-1">Certificate Date</label>
                            <input type="date" name="tds_certificate_date"
                                   class="form-control form-control-sm"
                                   value="{{ old('tds_certificate_date') }}">
                            @error('tds_certificate_date')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="small text-muted mt-2">
                        Note: This is for tracking only. Accounting entry for <strong>TDS Receivable</strong> is posted when you post the Client RA Bill.
                    </div>
                </div>


                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">Save Receipt</button>
                    <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const partySelect   = document.getElementById('party_account_id');
    const dateInput    = document.getElementById('voucher_date');
    const tbody         = document.getElementById('client-bill-rows');
    const oldAlloc      = @json(old('receipt_allocations', []));
    let allocMap        = {};

    const tdsSectionSel     = document.getElementById('tds_section');
    const tdsRateInput      = document.getElementById('tds_rate');
    const expectedTdsInput  = document.getElementById('expected_tds_amount');

    if (Array.isArray(oldAlloc)) {
        oldAlloc.forEach(function (row, index) {
            if (row && row.bill_id) {
                allocMap[Number(row.bill_id)] = parseFloat(row.amount || 0) || 0;
            }
        });
    }

    function renderRows(bills) {
        tbody.innerHTML = '';

        if (!bills || !bills.length) {
            const tr = document.createElement('tr');
            tr.classList.add('text-muted');
            tr.innerHTML = '<td colspan="5" class="text-center small py-2">No open client bills for this ledger.</td>';
            tbody.appendChild(tr);
            if (expectedTdsInput) { expectedTdsInput.value = '0.00'; }
            return;
        }

        bills.forEach(function (bill, idx) {
            const tr = document.createElement('tr');
            const key = bill.id;
            const oldAmount = allocMap[key] || '';

            const total = typeof bill.total_amount === 'number' ? bill.total_amount : parseFloat(bill.total_amount || 0) || 0;
            const outstd = typeof bill.outstanding_amount === 'number' ? bill.outstanding_amount : parseFloat(bill.outstanding_amount || 0) || 0;

            tr.innerHTML = ''
                + '<td class="small">' + bill.bill_number + '</td>'
                + '<td class="small">' + (bill.bill_date || '') + '</td>'
                + '<td class="small text-end">' + total.toFixed(2) + '</td>'
                + '<td class="small text-end">' + outstd.toFixed(2) + '</td>'
                + '<td>'
                + '  <input type="hidden" name="receipt_allocations[' + idx + '][bill_id]" value="' + bill.id + '">'
                + '  <input type="number" step="0.01"'
                + '         name="receipt_allocations[' + idx + '][amount]"'
                + '         class="form-control form-control-sm"'
                + '         data-tds-amount="' + ((bill.tds_amount !== undefined && bill.tds_amount !== null) ? bill.tds_amount : 0) + '"'
                + '         data-receivable-amount="' + ((bill.receivable_amount !== undefined && bill.receivable_amount !== null) ? bill.receivable_amount : total) + '"'
                + '         data-tds-section="' + ((bill.tds_section !== undefined && bill.tds_section !== null) ? bill.tds_section : '') + '"'
                + '         data-tds-rate="' + ((bill.tds_rate !== undefined && bill.tds_rate !== null) ? bill.tds_rate : 0) + '"'
                + '         max="' + outstd + '"'
                + '         value="' + (oldAmount !== '' ? oldAmount : '') + '">'
                + '</td>';

            tbody.appendChild(tr);
        });

    }

    function recalcExpectedTds() {
        if (!expectedTdsInput) {
            return;
        }

        let expected = 0.0;
        const sections = new Set();
        const rates = new Set();

        const amountInputs = tbody.querySelectorAll('input[name*="[amount]"]');

        amountInputs.forEach(function (inp) {
            const alloc = parseFloat(inp.value || 0) || 0;
            if (alloc <= 0) {
                return;
            }

            const billTds = parseFloat(inp.dataset.tdsAmount || 0) || 0;
            let billRecv = parseFloat(inp.dataset.receivableAmount || 0) || 0;

            if (billTds <= 0 || billRecv <= 0) {
                return;
            }

            let ratio = alloc / billRecv;
            if (ratio > 1) ratio = 1;
            if (ratio < 0) ratio = 0;

            expected += (billTds * ratio);

            const sec = (inp.dataset.tdsSection || '').trim();
            if (sec) sections.add(sec);

            const rate = parseFloat(inp.dataset.tdsRate || 0) || 0;
            if (rate > 0) rates.add(rate.toFixed(4));
        });

        expectedTdsInput.value = expected.toFixed(2);

        // Auto-fill section if empty and exactly one section is detected from allocated bills
        if (tdsSectionSel && !tdsSectionSel.value && sections.size === 1) {
            const only = Array.from(sections)[0];
            tdsSectionSel.value = only;
        }

        // Auto-fill rate if empty
        if (tdsRateInput && (!tdsRateInput.value || parseFloat(tdsRateInput.value) === 0)) {
            if (rates.size === 1) {
                tdsRateInput.value = Array.from(rates)[0];
            } else if (tdsSectionSel && tdsSectionSel.value) {
                const opt = tdsSectionSel.options[tdsSectionSel.selectedIndex];
                const rateAttr = opt ? opt.getAttribute('data-rate') : '';
                if (rateAttr) {
                    tdsRateInput.value = rateAttr;
                }
            }
        }
    }

    async function loadBills() {
        const accountId = partySelect.value;

        if (!accountId) {
            tbody.innerHTML = '<tr class="text-muted">'
                + '<td colspan="5" class="text-center small py-2">Select a client/debtor ledger to load open bills.</td>'
                + '</tr>';
            if (expectedTdsInput) { expectedTdsInput.value = '0.00'; }
            return;
        }

        try {
            const asOfDate = dateInput && dateInput.value ? dateInput.value : '';
            const url  = "{{ route('accounting.api.open-client-bills') }}"
                + '?party_account_id=' + encodeURIComponent(accountId)
                + (asOfDate ? '&as_of_date=' + encodeURIComponent(asOfDate) : '')
                + '&status=posted';
            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load bills');
            }

            const json = await resp.json();
            renderRows(json.data || []);
            recalcExpectedTds();
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr class="text-danger">'
                + '<td colspan="5" class="text-center small py-2">Could not load client bills. Please retry or contact admin.</td>'
                + '</tr>';
            if (expectedTdsInput) { expectedTdsInput.value = '0.00'; }
        }
    }


    if (tbody) {
        tbody.addEventListener('input', function (e) {
            if (e && e.target && e.target.name && e.target.name.includes('[amount]')) {
                recalcExpectedTds();
            }
        });
    }

    if (tdsSectionSel) {
        tdsSectionSel.addEventListener('change', function () {
            if (!tdsRateInput) return;

            const opt = tdsSectionSel.options[tdsSectionSel.selectedIndex];
            const rateAttr = opt ? opt.getAttribute('data-rate') : '';
            if (rateAttr && (!tdsRateInput.value || parseFloat(tdsRateInput.value) === 0)) {
                tdsRateInput.value = rateAttr;
            }
        });
    }

    if (partySelect) {
        partySelect.addEventListener('change', loadBills);

        if (dateInput) {
            dateInput.addEventListener('change', loadBills);
        }

        if (partySelect.value) {
            loadBills();
        }
    }
});
</script>
@endpush

@include('accounting.vouchers._prevent_enter_submit')
