@extends('layouts.erp')

@section('title', 'Receipt Voucher')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="h4 mb-0">Receipt Voucher</h1>
        <a href="{{ route('accounting.receipts.index') }}" class="btn btn-outline-secondary btn-sm">Back To Receipts</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.receipts.store') }}" data-prevent-enter-submit="1" autocomplete="off">
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
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Bank / Cash Account</label>
                        <select name="bank_account_id" id="bank_account_id"
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
                        <div class="form-text" id="bank-account-balance-text">Select a bank or cash account to view current balance.</div>
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
                        <div class="form-text" id="payee-outstanding-text">Select a client ledger to view total outstanding.</div>
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
                        Select client ledger to load open receivable bills.
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">Select</th>
                                <th style="width: 18%;">Bill No</th>
                                <th style="width: 12%;">Bill Date</th>
                                <th style="width: 12%;">Invoice Amt</th>
                                <th style="width: 12%;">TDS</th>
                                <th style="width: 12%;">Receivable</th>
                                <th style="width: 12%;">Outstanding</th>
                                <th style="width: 24%;">Allocate Now</th>
                            </tr>
                        </thead>
                        <tbody id="client-bill-rows">
                            <tr class="text-muted">
                                <td colspan="8" class="text-center small py-2">
                                    Select a client/debtor ledger to load open bills for allocation.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end">Total Allocated</th>
                                <th class="text-end" id="receipt-allocated-total">0.00</th>
                            </tr>
                        </tfoot>
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

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light-subtle">
                            <div class="text-muted small">Total Receipt</div>
                            <div class="fw-semibold" id="summary-total-receipt">0.00</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light-subtle">
                            <div class="text-muted small">Total Allocated</div>
                            <div class="fw-semibold" id="summary-total-allocated">0.00</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light-subtle">
                            <div class="text-muted small">On Account</div>
                            <div class="fw-semibold" id="summary-on-account">0.00</div>
                        </div>
                    </div>
                </div>
                <div class="small text-danger mb-3 d-none" id="allocation-warning"></div>

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
    const bankSelect    = document.getElementById('bank_account_id');
    const partySelect   = document.getElementById('party_account_id');
    const dateInput    = document.getElementById('voucher_date');
    const amountInput  = document.getElementById('voucher_amount');
    const tbody         = document.getElementById('client-bill-rows');
    const allocatedFoot = document.getElementById('receipt-allocated-total');
    const totalReceipt = document.getElementById('summary-total-receipt');
    const totalAllocated = document.getElementById('summary-total-allocated');
    const onAccountAmount = document.getElementById('summary-on-account');
    const warningBox = document.getElementById('allocation-warning');
    const bankBalanceText = document.getElementById('bank-account-balance-text');
    const payeeOutstandingText = document.getElementById('payee-outstanding-text');
    const oldAlloc      = @json(old('receipt_allocations', []));
    const accountSummaryUrl = @json(route('accounting.api.account-summary'));
    let allocMap        = {};
    let selectedMap     = {};

    const tdsSectionSel     = document.getElementById('tds_section');
    const tdsRateInput      = document.getElementById('tds_rate');
    const expectedTdsInput  = document.getElementById('expected_tds_amount');

    if (Array.isArray(oldAlloc)) {
        oldAlloc.forEach(function (row) {
            if (row && row.bill_id) {
                allocMap[Number(row.bill_id)] = parseFloat(row.amount || 0) || 0;
                if (parseFloat(row.amount || 0) > 0 || row.selected) {
                    selectedMap[Number(row.bill_id)] = true;
                }
            }
        });
    }

    function parseNumber(value) {
        const parsed = parseFloat(value || 0);

        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
        return parseNumber(value).toFixed(2);
    }

    function formatBalance(amount, side) {
        return money(amount) + ' ' + String(side || 'dr').toUpperCase();
    }

    function currentReceiptAmount() {
        return parseNumber(amountInput ? amountInput.value : 0);
    }

    function currentAllocatedTotal() {
        let total = 0;

        tbody.querySelectorAll('tr').forEach(function (row) {
            const input = row.querySelector('input[data-role="allocate-amount"]');
            const checkbox = row.querySelector('input[data-role="allocate-select"]');
            const selectedFlag = row.querySelector('input[data-role="selected-flag"]');

            if (!input || input.disabled) {
                return;
            }

            const isSelected = (checkbox && checkbox.checked)
                || (selectedFlag && selectedFlag.value === '1');

            if (!isSelected) {
                return;
            }

            total += parseNumber(input.value);
        });

        return total;
    }

    function updateSummary() {
        const receiptAmount = currentReceiptAmount();
        const allocated = currentAllocatedTotal();
        const onAccount = receiptAmount - allocated;

        totalReceipt.textContent = money(receiptAmount);
        totalAllocated.textContent = money(allocated);
        onAccountAmount.textContent = money(Math.max(onAccount, 0));
        allocatedFoot.textContent = money(allocated);

        if (onAccount < -0.009) {
            warningBox.textContent = 'Allocated amount cannot exceed the receipt amount.';
            warningBox.classList.remove('d-none');
        } else {
            warningBox.textContent = '';
            warningBox.classList.add('d-none');
        }
    }

    function renderRows(bills) {
        tbody.innerHTML = '';

        if (!bills || !bills.length) {
            const tr = document.createElement('tr');
            tr.classList.add('text-muted');
            tr.innerHTML = '<td colspan="8" class="text-center small py-2">No open client bills for this ledger.</td>';
            tbody.appendChild(tr);
            if (expectedTdsInput) { expectedTdsInput.value = '0.00'; }
            updateSummary();
            return;
        }

        bills.forEach(function (bill, idx) {
            const tr = document.createElement('tr');
            const key = bill.id;
            const oldAmount = allocMap[key] || '';
            const checked = selectedMap[key] || parseNumber(oldAmount) > 0;

            const total = typeof bill.invoice_amount === 'number'
                ? bill.invoice_amount
                : (typeof bill.total_amount === 'number' ? bill.total_amount : parseFloat(bill.total_amount || 0) || 0);
            const tds = typeof bill.tds_amount === 'number' ? bill.tds_amount : parseFloat(bill.tds_amount || 0) || 0;
            const receivable = typeof bill.receivable_amount === 'number'
                ? bill.receivable_amount
                : parseFloat(bill.receivable_amount || 0) || total;
            const outstd = typeof bill.outstanding_amount === 'number' ? bill.outstanding_amount : parseFloat(bill.outstanding_amount || 0) || 0;

            tr.innerHTML = ''
                + '<td class="text-center">'
                + '  <input type="checkbox"'
                + '         class="form-check-input mt-0"'
                + '         data-role="allocate-select"'
                + '         data-outstanding="' + outstd + '"'
                + '         ' + (checked ? 'checked' : '') + '>'
                + '</td>'
                + '<td class="small">'
                + '  <div>' + bill.bill_number + '</div>'
                + '  ' + (bill.bill_reference && bill.bill_reference !== bill.bill_number
                        ? '<div class="text-muted" style="font-size:11px;">Inv: ' + bill.bill_reference + '</div>'
                        : '')
                + '</td>'
                + '<td class="small">' + (bill.bill_date || '') + '</td>'
                + '<td class="small text-end">' + total.toFixed(2) + '</td>'
                + '<td class="small text-end">' + tds.toFixed(2) + '</td>'
                + '<td class="small text-end">' + receivable.toFixed(2) + '</td>'
                + '<td class="small text-end">' + outstd.toFixed(2) + '</td>'
                + '<td>'
                + '  <input type="hidden" name="receipt_allocations[' + idx + '][bill_id]" value="' + bill.id + '">'
                + '  <input type="hidden" name="receipt_allocations[' + idx + '][selected]" value="' + (checked ? '1' : '') + '" data-role="selected-flag">'
                + '  <input type="number" step="0.01"'
                + '         name="receipt_allocations[' + idx + '][amount]"'
                + '         class="form-control form-control-sm"'
                + '         data-role="allocate-amount"'
                + '         autocomplete="off"'
                + '         data-tds-amount="' + ((bill.tds_amount !== undefined && bill.tds_amount !== null) ? bill.tds_amount : 0) + '"'
                + '         data-receivable-amount="' + ((bill.receivable_amount !== undefined && bill.receivable_amount !== null) ? bill.receivable_amount : total) + '"'
                + '         data-tds-section="' + ((bill.tds_section !== undefined && bill.tds_section !== null) ? bill.tds_section : '') + '"'
                + '         data-tds-rate="' + ((bill.tds_rate !== undefined && bill.tds_rate !== null) ? bill.tds_rate : 0) + '"'
                + '         max="' + outstd + '"'
                + '         value="' + (oldAmount !== '' ? oldAmount : '') + '"'
                + '         ' + (checked ? '' : 'disabled') + '>'
                + '</td>';

            tbody.appendChild(tr);
        });

        bindAllocationRowEvents();
        updateSummary();
    }

    function bindAllocationRowEvents() {
        tbody.querySelectorAll('tr').forEach(function (row) {
            const checkbox = row.querySelector('input[data-role="allocate-select"]');
            const amountField = row.querySelector('input[data-role="allocate-amount"]');
            const selectedFlag = row.querySelector('input[data-role="selected-flag"]');

            if (!checkbox || !amountField || !selectedFlag) {
                return;
            }

            checkbox.addEventListener('change', function () {
                amountField.disabled = !checkbox.checked;
                selectedFlag.value = checkbox.checked ? '1' : '';

                if (checkbox.checked) {
                    if (parseNumber(amountField.value) <= 0) {
                        amountField.value = money(checkbox.dataset.outstanding);
                    }
                    amountField.focus();
                } else {
                    amountField.value = '';
                }

                updateSummary();
                recalcExpectedTds();
            });

            amountField.addEventListener('input', function () {
                const cap = parseNumber(amountField.max);
                let value = parseNumber(amountField.value);

                if (value > cap) {
                    value = cap;
                    amountField.value = money(value);
                }

                if (value > 0) {
                    checkbox.checked = true;
                    amountField.disabled = false;
                    selectedFlag.value = '1';
                } else {
                    checkbox.checked = false;
                    selectedFlag.value = '';
                }

                updateSummary();
                recalcExpectedTds();
            });
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
                + '<td colspan="6" class="text-center small py-2">Select a client/debtor ledger to load open bills.</td>'
                + '</tr>';
            if (expectedTdsInput) { expectedTdsInput.value = '0.00'; }
            updateSummary();
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
                + '<td colspan="8" class="text-center small py-2">Could not load client bills. Please retry or contact admin.</td>'
                + '</tr>';
            if (expectedTdsInput) { expectedTdsInput.value = '0.00'; }
            updateSummary();
        }
    }

    async function loadBankSummary() {
        if (!bankSelect || !bankBalanceText) {
            return;
        }

        const accountId = bankSelect.value;
        if (!accountId) {
            bankBalanceText.textContent = 'Select a bank or cash account to view current balance.';
            return;
        }

        try {
            const asOfDate = dateInput && dateInput.value ? dateInput.value : '';
            const url = accountSummaryUrl
                + '?role=bank&account_id=' + encodeURIComponent(accountId)
                + (asOfDate ? '&as_of_date=' + encodeURIComponent(asOfDate) : '');

            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load bank balance');
            }

            const json = await resp.json();
            const data = json.data || {};
            bankBalanceText.textContent = 'Current balance: ' + formatBalance(data.balance || 0, data.balance_side || 'dr');
        } catch (error) {
            console.error(error);
            bankBalanceText.textContent = 'Could not load current balance.';
        }
    }

    async function loadPayeeSummary() {
        if (!partySelect || !payeeOutstandingText) {
            return;
        }

        const accountId = partySelect.value;
        if (!accountId) {
            payeeOutstandingText.textContent = 'Select a client ledger to view total outstanding.';
            return;
        }

        try {
            const asOfDate = dateInput && dateInput.value ? dateInput.value : '';
            const url = accountSummaryUrl
                + '?role=client&account_id=' + encodeURIComponent(accountId)
                + (asOfDate ? '&as_of_date=' + encodeURIComponent(asOfDate) : '');

            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load client outstanding');
            }

            const json = await resp.json();
            const data = json.data || {};
            payeeOutstandingText.textContent = 'Total outstanding: ' + money(data.total_outstanding || 0);
        } catch (error) {
            console.error(error);
            payeeOutstandingText.textContent = 'Could not load total outstanding.';
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
        partySelect.addEventListener('change', function () {
            loadPayeeSummary();
            loadBills();
        });

        if (dateInput) {
            dateInput.addEventListener('change', function () {
                loadBankSummary();
                loadPayeeSummary();
                loadBills();
            });
        }

        if (partySelect.value) {
            loadPayeeSummary();
            loadBills();
        }
    }

    if (bankSelect) {
        bankSelect.addEventListener('change', loadBankSummary);

        if (bankSelect.value) {
            loadBankSummary();
        }
    }

    if (amountInput) {
        amountInput.addEventListener('input', updateSummary);
    }

    updateSummary();
});
</script>
@endpush

@include('accounting.vouchers._prevent_enter_submit')
