@extends('layouts.erp')

@section('title', 'Payment Voucher')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="h4 mb-0">Payment Voucher</h1>
        <a href="{{ route('accounting.payments.index') }}" class="btn btn-outline-secondary btn-sm">Back To Payments</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.payments.store') }}" data-prevent-enter-submit="1" autocomplete="off">
                @csrf

                <input type="hidden" name="company_id" value="{{ $companyId }}">
                @php($partyModelClass = \App\Models\Party::class)

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
                        <label class="form-label form-label-sm">Payee Ledger (Supplier / Subcontractor / Expense)</label>
                        <select name="party_account_id" id="party_account_id"
                                class="form-select form-select-sm @error('party_account_id') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($counterpartyAccounts as $acc)
                                <option value="{{ $acc->id }}"
                                    data-related-model-type="{{ $acc->related_model_type }}"
                                    data-is-supplier="{{ !empty($acc->party_is_supplier) ? '1' : '0' }}"
                                    data-is-contractor="{{ !empty($acc->party_is_contractor) ? '1' : '0' }}"
                                    @selected(old('party_account_id') == $acc->id)>
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('party_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="payee-outstanding-text">Select a payee ledger to view total outstanding.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Payment Type</label>
                        <select name="payment_type" id="payment_type"
                                class="form-select form-select-sm @error('payment_type') is-invalid @enderror">
                            <option value="bill_allocation" @selected(old('payment_type', 'bill_allocation') === 'bill_allocation')>Bill Allocation</option>
                            <option value="on_account" @selected(old('payment_type') === 'on_account')>On Account</option>
                            <option value="advance_po" @selected(old('payment_type') === 'advance_po')>Advance Against PO</option>
                            <option value="advance_work_order" @selected(old('payment_type') === 'advance_work_order')>Advance Against Work Order</option>
                        </select>
                        @error('payment_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Amount</label>
                        <input type="number" step="0.01" name="amount" id="voucher_amount"
                               class="form-control form-control-sm @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}">
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8 d-none" id="purchase-order-block">
                        <label class="form-label form-label-sm">Purchase Order Reference</label>
                        <select name="purchase_order_id" id="purchase_order_id"
                                class="form-select form-select-sm @error('purchase_order_id') is-invalid @enderror">
                            <option value="">-- Select supplier and load PO --</option>
                        </select>
                        @error('purchase_order_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Required only for advance payments against a purchase order.</div>
                    </div>

                    <div class="col-md-8 d-none" id="work-order-block">
                        <label class="form-label form-label-sm">Subcontractor Work Order Reference</label>
                        <select name="subcontractor_work_order_id" id="subcontractor_work_order_id"
                                class="form-select form-select-sm @error('subcontractor_work_order_id') is-invalid @enderror">
                            <option value="">-- Select subcontractor and load work order --</option>
                        </select>
                        @error('subcontractor_work_order_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Required only for subcontractor advances against a work order.</div>
                    </div>
                </div>

                <hr class="mt-3 mb-2">

                <div class="mb-2 d-flex justify-content-between align-items-center" id="bill-allocation-header">
                    <div>
                        <span class="fw-semibold small">Bill allocations (Purchase Bills / Subcontractor RA Bills)</span>
                        <span class="text-muted small ms-2">(optional)</span>
                    </div>
                    <div class="small text-muted">
                        Select supplier or subcontractor ledger to load open bills.
                    </div>
                </div>

                <div class="table-responsive mb-2" id="bill-allocation-block">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">Select</th>
                                <th style="width: 18%;">Bill No</th>
                                <th style="width: 16%;">Bill Date</th>
                                <th style="width: 16%;">Bill Amount</th>
                                <th style="width: 16%;">Outstanding</th>
                                <th style="width: 24%;">Allocate Now</th>
                            </tr>
                        </thead>
                        <tbody id="purchase-bill-rows">
                            <tr class="text-muted">
                                <td colspan="6" class="text-center small py-2">
                                    Select a supplier or subcontractor ledger to load open payable bills.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end">Total Allocated</th>
                                <th class="text-end" id="bill-allocated-total">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @error('purchase_allocations.*.amount')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror
                @error('purchase_allocations.*.bill_id')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror

                <div class="small text-muted mb-3" id="payment-type-help">
                    You can partially allocate the payment. Any balance left after selected bill allocations will stay on account.
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light-subtle">
                            <div class="text-muted small">Total Payment</div>
                            <div class="fw-semibold" id="summary-total-payment">0.00</div>
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
                            <div class="text-muted small" id="summary-balance-label">On Account</div>
                            <div class="fw-semibold" id="summary-balance-amount">0.00</div>
                        </div>
                    </div>
                </div>
                <div class="small text-danger mb-3 d-none" id="allocation-warning"></div>

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

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">Save Payment</button>
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
    const paymentType   = document.getElementById('payment_type');
    const dateInput     = document.getElementById('voucher_date');
    const amountInput   = document.getElementById('voucher_amount');
    const tbody         = document.getElementById('purchase-bill-rows');
    const poBlock       = document.getElementById('purchase-order-block');
    const poSelect      = document.getElementById('purchase_order_id');
    const workOrderBlock = document.getElementById('work-order-block');
    const workOrderSelect = document.getElementById('subcontractor_work_order_id');
    const billBlock     = document.getElementById('bill-allocation-block');
    const billHeader    = document.getElementById('bill-allocation-header');
    const helpText      = document.getElementById('payment-type-help');
    const allocatedFoot = document.getElementById('bill-allocated-total');
    const totalPayment  = document.getElementById('summary-total-payment');
    const totalAllocated = document.getElementById('summary-total-allocated');
    const balanceLabel  = document.getElementById('summary-balance-label');
    const balanceAmount = document.getElementById('summary-balance-amount');
    const warningBox    = document.getElementById('allocation-warning');
    const bankBalanceText = document.getElementById('bank-account-balance-text');
    const payeeOutstandingText = document.getElementById('payee-outstanding-text');
    const oldAlloc      = @json(old('purchase_allocations', []));
    const oldPaymentType = @json(old('payment_type', 'bill_allocation'));
    const oldPurchaseOrderId = @json(old('purchase_order_id'));
    const oldWorkOrderId = @json(old('subcontractor_work_order_id'));
    const partyModelClass = @json(\App\Models\Party::class);
    const accountSummaryUrl = @json(route('accounting.api.account-summary'));
    const originalPartyOptions = partySelect
        ? Array.from(partySelect.options).map(function (option) {
            return {
                value: option.value,
                text: option.text,
                relatedModelType: option.dataset.relatedModelType || '',
                isSupplier: option.dataset.isSupplier || '0',
                isContractor: option.dataset.isContractor || '0',
                selected: option.selected,
            };
        })
        : [];
    let allocMap        = {};
    let selectedMap     = {};

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

    function getSelectedPartyOption() {
        return partySelect && partySelect.selectedIndex >= 0
            ? partySelect.options[partySelect.selectedIndex]
            : null;
    }

    function optionMatchesPaymentType(option, type) {
        if (!option.value) {
            return true;
        }

        if (type === 'advance_po') {
            return option.isSupplier === '1';
        }

        if (type === 'advance_work_order') {
            return option.isContractor === '1';
        }

        if (type === 'bill_allocation') {
            return option.isSupplier === '1' || option.isContractor === '1';
        }

        if (type === 'on_account') {
            return true;
        }

        if (!type) {
            return option.relatedModelType !== partyModelClass;
        }

        return true;
    }

    function rebuildPartyOptions() {
        if (!partySelect) {
            return;
        }

        const currentValue = partySelect.value;
        const type = paymentType ? paymentType.value : 'on_account';

        partySelect.innerHTML = '';

        originalPartyOptions.forEach(function (option) {
            if (!optionMatchesPaymentType(option, type)) {
                return;
            }

            const el = document.createElement('option');
            el.value = option.value;
            el.textContent = option.text;

            if (option.relatedModelType) {
                el.dataset.relatedModelType = option.relatedModelType;
            }
            el.dataset.isSupplier = option.isSupplier;
            el.dataset.isContractor = option.isContractor;

            if (currentValue && option.value === currentValue) {
                el.selected = true;
            }

            partySelect.appendChild(el);
        });

        if (currentValue && !Array.from(partySelect.options).some(function (option) { return option.value === currentValue; })) {
            partySelect.value = '';
        }
    }

    function isPartyLedgerSelected() {
        const option = getSelectedPartyOption();

        return !!option && option.dataset.relatedModelType === partyModelClass;
    }

    function isSupplierLedgerSelected() {
        const option = getSelectedPartyOption();

        return !!option
            && option.dataset.relatedModelType === partyModelClass
            && option.dataset.isSupplier === '1';
    }

    function isContractorLedgerSelected() {
        const option = getSelectedPartyOption();

        return !!option
            && option.dataset.relatedModelType === partyModelClass
            && option.dataset.isContractor === '1';
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

    function currentPaymentAmount() {
        return parseNumber(amountInput ? amountInput.value : 0);
    }

    function currentAllocatedTotal(exceptInput) {
        let total = 0;

        tbody.querySelectorAll('tr').forEach(function (row) {
            const input = row.querySelector('input[data-role="allocate-amount"]');
            const checkbox = row.querySelector('input[data-role="allocate-select"]');
            const selectedFlag = row.querySelector('input[data-role="selected-flag"]');

            if (!input || input === exceptInput || input.disabled) {
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
        const paymentAmount = currentPaymentAmount();
        const allocated = currentAllocatedTotal();
        const balance = paymentAmount - allocated;
        const label = (paymentType.value === 'advance_po' || paymentType.value === 'advance_work_order')
            ? 'Advance Amount'
            : 'On Account';

        totalPayment.textContent = money(paymentAmount);
        totalAllocated.textContent = money(allocated);
        balanceLabel.textContent = label;
        balanceAmount.textContent = money(Math.max(balance, 0));
        allocatedFoot.textContent = money(allocated);

        if (balance < -0.009) {
            warningBox.textContent = 'Allocated amount cannot exceed the payment amount.';
            warningBox.classList.remove('d-none');
        } else {
            warningBox.textContent = '';
            warningBox.classList.add('d-none');
        }
    }

    function updatePaymentTypeState() {
        const type = paymentType.value;
        const partyLedger = isPartyLedgerSelected();

        const currentType = type;
        const showBills = currentType === 'bill_allocation';
        const showPo = currentType === 'advance_po';
        const showWorkOrder = currentType === 'advance_work_order';

        billBlock.classList.toggle('d-none', !showBills);
        billHeader.classList.toggle('d-none', !showBills);
        poBlock.classList.toggle('d-none', !showPo);
        workOrderBlock.classList.toggle('d-none', !showWorkOrder);

        if (currentType === 'bill_allocation') {
            helpText.textContent = partyLedger
                ? 'Select the bills you want to settle. Any balance left after selected allocations will stay on account.'
                : 'Select a supplier or subcontractor payee ledger to load open bills.';
        } else if (currentType === 'advance_po') {
            helpText.textContent = isSupplierLedgerSelected()
                ? 'Advance payments must be tagged to a purchase order. The full payment amount will be stored as PO advance.'
                : 'Select a supplier payee ledger to use advance against PO.';
        } else if (currentType === 'advance_work_order') {
            helpText.textContent = isContractorLedgerSelected()
                ? 'Subcontractor advances must be tagged to a work order. The full payment amount will be stored as work-order advance.'
                : 'Select a subcontractor payee ledger to use advance against work order.';
        } else {
            helpText.textContent = 'No bill selection is required. The full payment amount will remain on account for supplier/subcontractor ledgers or act as a direct payment for non-party ledgers.';
        }

        updateSummary();
    }

    function buildPoOption(order) {
        const project = order.project_code ? ' | ' + order.project_code : '';
        const total = typeof order.total_amount === 'number' ? order.total_amount : parseNumber(order.total_amount);

        return '<option value="' + order.id + '">' + order.code + ' | ' + (order.po_date || '') + project + ' | ' + total.toFixed(2) + '</option>';
    }

    async function loadPurchaseOrders() {
        const accountId = partySelect.value;

        poSelect.innerHTML = '<option value="">-- Select supplier and load PO --</option>';

        if (!accountId || !isSupplierLedgerSelected()) {
            return;
        }

        try {
            const url = "{{ route('accounting.api.supplier-purchase-orders') }}"
                + '?party_account_id=' + encodeURIComponent(accountId);
            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load purchase orders');
            }

            const json = await resp.json();
            (json.data || []).forEach(function (order) {
                poSelect.insertAdjacentHTML('beforeend', buildPoOption(order));
            });

            if (oldPurchaseOrderId) {
                poSelect.value = String(oldPurchaseOrderId);
            }
        } catch (error) {
            console.error(error);
            poSelect.innerHTML = '<option value="">Could not load supplier purchase orders</option>';
        }
    }

    function buildWorkOrderOption(order) {
        const project = order.project_code ? ' | ' + order.project_code : '';
        const retention = typeof order.retention_percent === 'number' ? order.retention_percent : parseNumber(order.retention_percent);
        const security = typeof order.security_deposit_percent === 'number' ? order.security_deposit_percent : parseNumber(order.security_deposit_percent);

        return '<option value="' + order.id + '">' + order.code + ' | ' + (order.work_order_date || '')
            + project + ' | Ret ' + retention.toFixed(2) + '% | SD ' + security.toFixed(2) + '%</option>';
    }

    async function loadWorkOrders() {
        const accountId = partySelect.value;

        workOrderSelect.innerHTML = '<option value="">-- Select subcontractor and load work order --</option>';

        if (!accountId || !isContractorLedgerSelected()) {
            return;
        }

        try {
            const url = "{{ route('accounting.api.subcontractor-work-orders') }}"
                + '?party_account_id=' + encodeURIComponent(accountId);
            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load work orders');
            }

            const json = await resp.json();
            (json.data || []).forEach(function (order) {
                workOrderSelect.insertAdjacentHTML('beforeend', buildWorkOrderOption(order));
            });

            if (oldWorkOrderId) {
                workOrderSelect.value = String(oldWorkOrderId);
            }
        } catch (error) {
            console.error(error);
            workOrderSelect.innerHTML = '<option value="">Could not load subcontractor work orders</option>';
        }
    }

    function renderRows(bills) {
        tbody.innerHTML = '';

        if (!bills || !bills.length) {
            const tr = document.createElement('tr');
            tr.classList.add('text-muted');
            tr.innerHTML = '<td colspan="6" class="text-center small py-2">No open payable bills for this ledger (or it is not a supplier/subcontractor ledger).</td>';
            tbody.appendChild(tr);
            updateSummary();
            return;
        }

        bills.forEach(function (bill, idx) {
            const tr = document.createElement('tr');
            const key = bill.id;
            const oldAmount = allocMap[key] || '';
            const checked = selectedMap[key] || parseNumber(oldAmount) > 0;

            const total = typeof bill.total_amount === 'number' ? bill.total_amount : parseFloat(bill.total_amount || 0) || 0;
            const outstd = typeof bill.outstanding_amount === 'number' ? bill.outstanding_amount : parseFloat(bill.outstanding_amount || 0) || 0;
            const reference = bill.bill_reference ? '<div class="text-muted small">' + bill.bill_reference + '</div>' : '';

            tr.innerHTML = ''
                + '<td class="text-center">'
                + '  <input type="checkbox"'
                + '         class="form-check-input mt-0"'
                + '         data-role="allocate-select"'
                + '         data-outstanding="' + outstd + '"'
                + '         ' + (checked ? 'checked' : '') + '>'
                + '</td>'
                + '<td class="small"><div>' + (bill.bill_kind || 'Bill') + ' - ' + (bill.bill_number || '') + '</div>' + reference + '</td>'
                + '<td class="small">' + (bill.bill_date || '') + '</td>'
                + '<td class="small text-end">' + total.toFixed(2) + '</td>'
                + '<td class="small text-end">' + outstd.toFixed(2) + '</td>'
                + '<td>'
                + '  <input type="hidden" name="purchase_allocations[' + idx + '][bill_type]" value="' + (bill.bill_type || '') + '">'
                + '  <input type="hidden" name="purchase_allocations[' + idx + '][bill_id]" value="' + bill.id + '">' 
                + '  <input type="hidden" name="purchase_allocations[' + idx + '][selected]" value="' + (checked ? '1' : '') + '" data-role="selected-flag">'
                + '  <input type="number" step="0.01"'
                + '         name="purchase_allocations[' + idx + '][amount]"'
                + '         class="form-control form-control-sm"'
                + '         data-role="allocate-amount"'
                + '         autocomplete="off"'
                + '         max="' + outstd + '"'
                + '         value="' + (oldAmount !== '' ? oldAmount : '') + '"'
                + '         ' + (checked ? '' : 'disabled') + '>'
                + '</td>';

            tbody.appendChild(tr);
        });

        bindAllocationRowEvents();
        updateSummary();
    }

    function suggestedAllocation(outstanding) {
        return Math.max(0, parseNumber(outstanding));
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
                const outstanding = checkbox.dataset.outstanding;
                amountField.disabled = !checkbox.checked;
                selectedFlag.value = checkbox.checked ? '1' : '';

                if (checkbox.checked) {
                    if (parseNumber(amountField.value) <= 0) {
                        amountField.value = money(suggestedAllocation(outstanding));
                    }
                    amountField.focus();
                } else {
                    amountField.value = '';
                }

                updateSummary();
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
            });
        });
    }

    async function loadBills() {
        const accountId = partySelect.value;

        if (!accountId) {
            tbody.innerHTML = '<tr class="text-muted">'
                + '<td colspan="6" class="text-center small py-2">Select a supplier or subcontractor ledger to load open payable bills.</td>'
                + '</tr>';
            updateSummary();
            return;
        }

        try {
            const asOfDate = dateInput && dateInput.value ? dateInput.value : '';
            const url  = "{{ route('accounting.api.open-purchase-bills') }}"
                + '?party_account_id=' + encodeURIComponent(accountId)
                + (asOfDate ? '&as_of_date=' + encodeURIComponent(asOfDate) : '');

            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load bills');
            }

            const json = await resp.json();
            renderRows(json.data || []);
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr class="text-danger">'
                + '<td colspan="6" class="text-center small py-2">Could not load payable bills. Please retry or contact admin.</td>'
                + '</tr>';
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
            payeeOutstandingText.textContent = 'Select a payee ledger to view total outstanding.';
            return;
        }

        try {
            const asOfDate = dateInput && dateInput.value ? dateInput.value : '';
            const url = accountSummaryUrl
                + '?role=payee&account_id=' + encodeURIComponent(accountId)
                + (asOfDate ? '&as_of_date=' + encodeURIComponent(asOfDate) : '');

            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!resp.ok) {
                throw new Error('Failed to load payee outstanding');
            }

            const json = await resp.json();
            const data = json.data || {};
            payeeOutstandingText.textContent = 'Total outstanding: ' + money(data.total_outstanding || 0);
        } catch (error) {
            console.error(error);
            payeeOutstandingText.textContent = 'Could not load total outstanding.';
        }
    }

    if (partySelect) {
        partySelect.addEventListener('change', function () {
            allocMap = {};
            selectedMap = {};
            updatePaymentTypeState();
            loadPayeeSummary();
            loadBills();
            loadPurchaseOrders();
            loadWorkOrders();
        });

        if (partySelect.value) {
            loadPayeeSummary();
            loadBills();
            loadPurchaseOrders();
            loadWorkOrders();
        }
    }

    if (bankSelect) {
        bankSelect.addEventListener('change', loadBankSummary);

        if (bankSelect.value) {
            loadBankSummary();
        }
    }

    if (dateInput) {
        dateInput.addEventListener('change', function () {
            loadBankSummary();
            loadPayeeSummary();
            if (partySelect && partySelect.value) {
                loadBills();
            }
        });
    }

    if (paymentType) {
        paymentType.addEventListener('change', function () {
            allocMap = {};
            selectedMap = {};
            rebuildPartyOptions();
            updatePaymentTypeState();

            if (paymentType.value === 'advance_po') {
                loadPurchaseOrders();
            } else if (paymentType.value === 'advance_work_order') {
                loadWorkOrders();
            } else if (paymentType.value === 'bill_allocation') {
                loadBills();
            }

            loadPayeeSummary();
        });
    }

    if (amountInput) {
        amountInput.addEventListener('input', updateSummary);
    }

    rebuildPartyOptions();
    paymentType.value = oldPaymentType;
    rebuildPartyOptions();
    updatePaymentTypeState();
    updateSummary();
});
</script>
@endpush

@include('accounting.vouchers._prevent_enter_submit')
