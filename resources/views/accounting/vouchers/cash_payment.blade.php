@extends('layouts.erp')

@section('title', 'Cash Payment Voucher')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="h4 mb-0">Cash Payment Voucher</h1>
        <a href="{{ route('accounting.cash-payments.index') }}" class="btn btn-outline-secondary btn-sm">Back To Cash Payments</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.cash-payments.store') }}" autocomplete="off">
                @csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}">

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Voucher No</label>
                        <input type="text" class="form-control form-control-sm" value="Auto-generated on Save" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Date</label>
                        <input type="date"
                               name="voucher_date"
                               id="voucher_date"
                               class="form-control form-control-sm @error('voucher_date') is-invalid @enderror"
                               value="{{ old('voucher_date', now()->toDateString()) }}">
                        @error('voucher_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Cash Account</label>
                        <select name="cash_account_id" id="cash_account_id" class="form-select form-select-sm @error('cash_account_id') is-invalid @enderror">
                            <option value="">-- Select --</option>
                            @foreach($cashAccounts as $account)
                                <option value="{{ $account->id }}" @selected(old('cash_account_id') == $account->id)>
                                    {{ $account->code ? $account->code . ' - ' : '' }}{{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('cash_account_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="cash-account-balance-text">Select a cash account to view available cash.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Reference</label>
                        <input type="text"
                               name="reference"
                               class="form-control form-control-sm @error('reference') is-invalid @enderror"
                               value="{{ old('reference') }}">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Project</label>
                        <select name="project_id" class="form-select form-select-sm @error('project_id') is-invalid @enderror">
                            <option value="">-- none --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                                    {{ $project->code }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Cost center will be auto-selected from the chosen project.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm">Narration</label>
                        <textarea name="narration"
                                  rows="2"
                                  class="form-control form-control-sm @error('narration') is-invalid @enderror">{{ old('narration') }}</textarea>
                        @error('narration')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold small">Expense Lines</div>
                        <div class="text-muted small">Use this screen for miscellaneous cash expenses only.</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="add-cash-line">Add Row</button>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 34%;">Expense Ledger</th>
                                <th>Description</th>
                                <th style="width: 14%;" class="text-end">Amount</th>
                                <th style="width: 10%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cash-line-body"></tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-end" id="cash-payment-total">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @error('lines')
                    <div class="text-danger small mb-3">{{ $message }}</div>
                @enderror

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">Save Cash Payment</button>
                    <a href="{{ route('accounting.cash-payments.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const body = document.getElementById('cash-line-body');
    const addButton = document.getElementById('add-cash-line');
    const totalEl = document.getElementById('cash-payment-total');
    const cashAccountSelect = document.getElementById('cash_account_id');
    const voucherDateInput = document.getElementById('voucher_date');
    const cashAccountBalanceText = document.getElementById('cash-account-balance-text');
    const accountSummaryUrl = @json(route('accounting.api.account-summary'));
    const expenseOptions = @json(
        $expenseAccounts->map(fn ($account) => [
            'id' => (int) $account->id,
            'label' => trim(($account->code ? $account->code . ' - ' : '') . $account->name),
        ])->values()
    );
    const oldLines = @json(old('lines', []));

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

    function optionsHtml(options, selectedValue, placeholder) {
        let html = '<option value="">' + placeholder + '</option>';
        options.forEach(function (option) {
            const selected = String(selectedValue || '') === String(option.id) ? ' selected' : '';
            html += '<option value="' + option.id + '"' + selected + '>' + option.label + '</option>';
        });
        return html;
    }

    function currentRows() {
        return Array.from(body.querySelectorAll('tr')).map(function (row) {
            return {
                account_id: row.querySelector('select[data-role="expense-account"]')?.value || '',
                description: row.querySelector('input[data-role="expense-description"]')?.value || '',
                amount: row.querySelector('input[data-role="expense-amount"]')?.value || '',
            };
        });
    }

    function normalizeRows(rows, preserveEmpty) {
        const normalized = Array.isArray(rows) ? rows.map(function (row) {
            return {
                account_id: String(row && row.account_id ? row.account_id : ''),
                description: String(row && row.description ? row.description : ''),
                amount: row && row.amount !== undefined && row.amount !== null ? String(row.amount) : '',
            };
        }) : [];

        const filtered = preserveEmpty ? normalized : normalized.filter(function (row) {
            return row.account_id !== '' || row.description !== '' || parseNumber(row.amount) > 0;
        });

        return filtered.length ? filtered : [{ account_id: '', description: '', amount: '' }];
    }

    function updateRemoveButtons() {
        const buttons = body.querySelectorAll('button[data-role="remove-line"]');
        const disableRemoval = buttons.length <= 1;
        buttons.forEach(function (button) {
            button.disabled = disableRemoval;
        });
    }

    function updateTotal() {
        let total = 0;
        body.querySelectorAll('input[data-role="expense-amount"]').forEach(function (input) {
            total += parseNumber(input.value);
        });
        totalEl.textContent = money(total);
    }

    async function loadCashBalance() {
        if (!cashAccountSelect || !cashAccountBalanceText) {
            return;
        }

        const accountId = cashAccountSelect.value;
        if (!accountId) {
            cashAccountBalanceText.textContent = 'Select a cash account to view available cash.';
            return;
        }

        try {
            const asOfDate = voucherDateInput && voucherDateInput.value ? voucherDateInput.value : '';
            const url = accountSummaryUrl
                + '?role=bank&account_id=' + encodeURIComponent(accountId)
                + (asOfDate ? '&as_of_date=' + encodeURIComponent(asOfDate) : '');

            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Failed to load cash balance');
            }

            const json = await response.json();
            const data = json.data || {};
            cashAccountBalanceText.textContent = 'Available cash: ' + formatBalance(data.balance || 0, data.balance_side || 'dr');
        } catch (error) {
            console.error(error);
            cashAccountBalanceText.textContent = 'Could not load available cash.';
        }
    }

    function bindEvents() {
        body.querySelectorAll('input[data-role="expense-amount"]').forEach(function (input) {
            input.addEventListener('input', updateTotal);
            input.addEventListener('blur', function () {
                const value = parseNumber(input.value);
                input.value = value > 0 ? money(value) : '';
                updateTotal();
            });
        });

        body.querySelectorAll('button[data-role="remove-line"]').forEach(function (button) {
            button.addEventListener('click', function () {
                const rows = currentRows();
                const index = Array.from(body.querySelectorAll('tr')).indexOf(button.closest('tr'));
                renderRows(rows.filter(function (_row, rowIndex) {
                    return rowIndex !== index;
                }));
            });
        });
    }

    function renderRows(rows) {
        const normalizedRows = normalizeRows(rows, true);
        body.innerHTML = '';

        normalizedRows.forEach(function (row, index) {
            const tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td>'
                + '  <select name="lines[' + index + '][account_id]" class="form-select form-select-sm" data-role="expense-account">'
                +        optionsHtml(expenseOptions, row.account_id, '-- select expense ledger --')
                + '  </select>'
                + '</td>'
                + '<td>'
                + '  <input type="text" name="lines[' + index + '][description]" class="form-control form-control-sm" data-role="expense-description" value="' + row.description.replace(/"/g, '&quot;') + '">'
                + '</td>'
                + '<td>'
                + '  <input type="number" step="0.01" name="lines[' + index + '][amount]" class="form-control form-control-sm text-end" data-role="expense-amount" value="' + (parseNumber(row.amount) > 0 ? money(row.amount) : '') + '">'
                + '</td>'
                + '<td class="text-center">'
                + '  <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-line">Remove</button>'
                + '</td>';

            body.appendChild(tr);
        });

        bindEvents();
        updateRemoveButtons();
        updateTotal();
    }

    addButton.addEventListener('click', function () {
        const rows = currentRows();
        rows.push({ account_id: '', description: '', amount: '' });
        renderRows(rows);
    });

    if (cashAccountSelect) {
        cashAccountSelect.addEventListener('change', loadCashBalance);
        if (cashAccountSelect.value) {
            loadCashBalance();
        }
    }

    if (voucherDateInput) {
        voucherDateInput.addEventListener('change', loadCashBalance);
    }

    renderRows(normalizeRows(oldLines, false));
});
</script>
@endpush
