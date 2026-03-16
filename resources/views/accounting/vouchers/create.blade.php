@extends('layouts.erp')

@section('title', $pageTitle ?? 'Create Voucher')

@section('content')
    @php
        $contraAccountLookup = array_flip(array_map('intval', $contraAccountIds ?? []));
        $fixedVoucherType = $fixedVoucherType ?? null;
        $effectiveVoucherType = $fixedVoucherType ?? old('voucher_type', 'journal');
        $formAction = $formAction ?? route('accounting.vouchers.store');
        $cancelRoute = $cancelRoute ?? route('accounting.vouchers.index');
    @endphp
    <div class="container-fluid">
        <h1 class="h4 mb-3">{{ $pageTitle ?? 'Create Voucher' }}</h1>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $formAction }}" data-prevent-enter-submit="1">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-2">
                            <label class="form-label form-label-sm">Company ID</label>
                            <input type="number" name="company_id" class="form-control form-control-sm"
                                value="{{ old('company_id', 1) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Voucher No.</label>
                            <input type="text" name="voucher_no" class="form-control form-control-sm"
                                value="{{ old('voucher_no') }}" placeholder="Leave blank to auto-generate">
                            <div class="form-text small">Leave blank to auto-generate from Voucher Series.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Type</label>
                            @if($fixedVoucherType)
                                <input type="hidden" name="voucher_type" id="voucher_type" value="{{ $effectiveVoucherType }}">
                                <input type="text" class="form-control form-control-sm" value="{{ $voucherTypes[$effectiveVoucherType] ?? ucfirst($effectiveVoucherType) }}" disabled>
                            @else
                                <select name="voucher_type" id="voucher_type" class="form-select form-select-sm">
                                    @foreach($voucherTypes as $key => $label)
                                        <option value="{{ $key }}" @selected($effectiveVoucherType === $key)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm">Date</label>
                            <input type="date" name="voucher_date" class="form-control form-control-sm"
                                value="{{ old('voucher_date', now()->toDateString()) }}">
                        </div>
                        <input type="hidden" name="exchange_rate" value="1">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Project</label>
                            <select name="project_id" id="project_id" class="form-select form-select-sm">
                                <option value="">-- none --</option>
                                @foreach($projects as $p)
                                    <option value="{{ $p->id }}" data-cost-center-id="{{ $projectCostCenterMap[$p->id] ?? '' }}"
                                        @selected((string) old('project_id') === (string) $p->id)>
                                        {{ $p->code ? ($p->code . ' - ') : '' }}{{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Cost Center</label>

                            <select id="cost_center_id" class="form-select form-select-sm" disabled>
                                <option value="">-- none --</option>
                                @foreach($costCenters as $cc)
                                    <option value="{{ $cc->id }}" @selected((string) old('cost_center_id') === (string) $cc->id)>
                                        {{ $cc->name }}
                                    </option>
                                @endforeach
                            </select>

                            <input type="hidden" name="cost_center_id" id="cost_center_id_hidden"
                                value="{{ old('cost_center_id') }}">
                        </div>

                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-sm">Narration</label>
                        <textarea name="narration" class="form-control form-control-sm"
                            rows="2">{{ old('narration') }}</textarea>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5>Voucher Lines</h5>
                        <button type="button" id="add-line" class="btn btn-sm btn-primary">Add Line</button>
                    </div>

                    <table class="table table-sm" id="voucher-lines-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account</th>
                                <th>Cost Center</th>
                                @if($hasMachineLineDimension)
                                    <th>Machine</th>
                                @endif
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for($i = 0; $i < 2; $i++)
                                <tr>
                                    <td class="row-number">{{ $i + 1 }}</td>
                                    <td>
                                        <select name="lines[{{ $i }}][account_id]"
                                            class="form-select form-select-sm voucher-account-select">
                                            <option value="">-- select --</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}"
                                                    data-contra-allowed="{{ isset($contraAccountLookup[(int) $account->id]) ? '1' : '0' }}"
                                                    @selected((string) old('lines.' . $i . '.account_id') === (string) $account->id)>
                                                    {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm line-cost-center">
                                            <option value="">-- none --</option>
                                            @foreach($costCenters as $cc)
                                                <option value="{{ $cc->id }}">{{ $cc->name }}</option>
                                            @endforeach
                                        </select>

                                        <input type="hidden" name="lines[{{ $i }}][cost_center_id]"
                                            class="line-cost-center-hidden"
                                            value="{{ old('lines.' . $i . '.cost_center_id') }}">
                                    </td>
                                    @if($hasMachineLineDimension)
                                        <td>
                                            <select name="lines[{{ $i }}][machine_id]" class="form-select form-select-sm">
                                                <option value="">-- none --</option>
                                                @foreach($machines as $machine)
                                                    <option value="{{ $machine->id }}" @selected((string) old('lines.' . $i . '.machine_id') === (string) $machine->id)>
                                                        {{ $machine->code ? ($machine->code . ' - ') : '' }}{{ $machine->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif
                                    <td>
                                        <input type="text" name="lines[{{ $i }}][description]"
                                            class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="lines[{{ $i }}][debit]"
                                            class="form-control form-control-sm debit">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="lines[{{ $i }}][credit]"
                                            class="form-control form-control-sm credit">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger remove-line">Remove</button>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="{{ $hasMachineLineDimension ? 5 : 4 }}" class="text-end">Total</th>
                                <th id="total-debit">0.00</th>
                                <th id="total-credit">0.00</th>
                                <th id="total-diff">0.00</th>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="mt-3">
                        <button type="submit" id="save-draft" class="btn btn-primary btn-sm" disabled>Save Draft</button>
                        @can('accounting.vouchers.update')
                            <button type="submit" id="save-post" name="post_now" value="1" class="btn btn-success btn-sm"
                                disabled>Save & Post</button>
                        @endcan
                    </div>

                    <script>
                        let lineIndex = 2; // Start index for new rows

                        /**
                         * Update row numbers and input names after adding/removing rows
                         */
                        function updateRows() {

                            const rows = document.querySelectorAll('#voucher-lines-table tbody tr');

                            rows.forEach((row, index) => {

                                row.querySelector('.row-number').textContent = index + 1;

                                row.querySelectorAll('input[name], select[name]').forEach(input => {

                                    input.name = input.name.replace(/lines\[\d+\]/, `lines[${index}]`);

                                });

                            });

                            syncContraAccountOptions();
                            calculateTotals();

                        }
                        function applyCostCenterToLines(costCenterId) {

                            document.querySelectorAll('#voucher-lines-table tbody tr').forEach(row => {

                                const select = row.querySelector('.line-cost-center');
                                const hidden = row.querySelector('.line-cost-center-hidden');

                                if (costCenterId) {

                                    select.value = costCenterId;
                                    select.disabled = true;

                                    hidden.value = costCenterId;

                                } else {

                                    select.disabled = false;

                                    hidden.value = select.value;

                                }

                            });

                        }
                        /**
                         * Add a new voucher line
                         */
                        function addLine() {

    const tableBody = document.querySelector('#voucher-lines-table tbody');
    const newRow = document.createElement('tr');

    const selectedProject = document.getElementById('project_id');
    const selectedOption = selectedProject.options[selectedProject.selectedIndex];
    const mappedCostCenterId = selectedOption ? (selectedOption.dataset.costCenterId || '') : '';

    newRow.innerHTML = `
        <td class="row-number"></td>
        <td>
            <select name="lines[${lineIndex}][account_id]" class="form-select form-select-sm voucher-account-select">
                <option value="">-- select --</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}" data-contra-allowed="{{ isset($contraAccountLookup[(int) $account->id]) ? '1' : '0' }}">
                        {{ $account->name }}
                    </option>
                @endforeach
            </select>
        </td>

        <td>
            <select class="form-select form-select-sm line-cost-center">
                <option value="">-- none --</option>
                @foreach($costCenters as $cc)
                    <option value="{{ $cc->id }}">{{ $cc->name }}</option>
                @endforeach
            </select>

            <input type="hidden"
                name="lines[${lineIndex}][cost_center_id]"
                class="line-cost-center-hidden"
                value="${mappedCostCenterId}">
        </td>

        @if($hasMachineLineDimension)
        <td>
            <select name="lines[${lineIndex}][machine_id]" class="form-select form-select-sm">
                <option value="">-- none --</option>
                @foreach($machines as $machine)
                    <option value="{{ $machine->id }}">
                        {{ $machine->code ? ($machine->code . ' - ') : '' }}{{ $machine->name }}
                    </option>
                @endforeach
            </select>
        </td>
        @endif

        <td>
            <input type="text"
                name="lines[${lineIndex}][description]"
                class="form-control form-control-sm">
        </td>

        <td>
            <input type="number"
                step="0.01"
                name="lines[${lineIndex}][debit]"
                class="form-control form-control-sm debit">
        </td>

        <td>
            <input type="number"
                step="0.01"
                name="lines[${lineIndex}][credit]"
                class="form-control form-control-sm credit">
        </td>

        <td>
            <button type="button" class="btn btn-sm btn-danger remove-line">Remove</button>
        </td>
    `;

    tableBody.appendChild(newRow);

    // ⭐ auto select cost center
    if (mappedCostCenterId) {
        const select = newRow.querySelector('.line-cost-center');
        const hidden = newRow.querySelector('.line-cost-center-hidden');

        select.value = mappedCostCenterId;
        select.disabled = true;
        hidden.value = mappedCostCenterId;
    }

    lineIndex++;

    updateRows();
}

                        /**
                         * Remove a voucher line
                         */
                        function removeLine(e) {
                            if (e.target.classList.contains('remove-line')) {
                                e.target.closest('tr').remove();
                                updateRows();
                            }
                        }

                        /**
                         * Handle mutual exclusivity of debit and credit
                         */
                        function handleMutualExclusivity(e) {
                            const row = e.target.closest('tr');
                            const debitInput = row.querySelector('.debit');
                            const creditInput = row.querySelector('.credit');

                            if (e.target.classList.contains('debit')) {
                                creditInput.disabled = debitInput.value !== '' && parseFloat(debitInput.value) > 0;
                            }

                            if (e.target.classList.contains('credit')) {
                                debitInput.disabled = creditInput.value !== '' && parseFloat(creditInput.value) > 0;
                            }

                            calculateTotals();
                        }

                        /**
                         * Format debit/credit to 2 decimals on blur
                         */
                        function formatDecimal(e) {
                            if (e.target.classList.contains('debit') || e.target.classList.contains('credit')) {
                                let val = parseFloat(e.target.value);
                                if (isNaN(val)) val = 0;
                                e.target.value = val.toFixed(2);
                                calculateTotals();
                            }
                        }

                        /**
                         * Calculate totals and enable/disable buttons
                         */
                        //    function calculateTotals() {
                        //         let totalDebit = 0;
                        //         let totalCredit = 0;

                        //         document.querySelectorAll('#voucher-lines-table tbody tr').forEach(row => {
                        //             const debit = parseFloat(row.querySelector('.debit').value) || 0;
                        //             const credit = parseFloat(row.querySelector('.credit').value) || 0;
                        //             totalDebit += debit;
                        //             totalCredit += credit;
                        //         });

                        //         const totalDiff = totalDebit - totalCredit;

                        //         document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
                        //         document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
                        //         document.getElementById('total-diff').textContent = totalDiff.toFixed(2);

                        //         // Buttons active ONLY when totalDiff is 0
                        //         const buttons = [document.getElementById('save-draft'), document.getElementById('save-post')];
                        //         const enable = totalDiff === 0 && (totalDebit > 0 || totalCredit > 0); // also ensure totals > 0
                        //         buttons.forEach(btn => { if (btn) btn.disabled = !enable; });
                        //     }
                        function calculateTotals() {
                            let totalDebit = 0;
                            let totalCredit = 0;

                            document.querySelectorAll('#voucher-lines-table tbody tr').forEach(row => {
                                const debit = parseFloat(row.querySelector('.debit').value) || 0;
                                const credit = parseFloat(row.querySelector('.credit').value) || 0;
                                totalDebit += debit;
                                totalCredit += credit;
                            });

                            const totalDiff = totalDebit - totalCredit;

                            document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
                            document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
                            document.getElementById('total-diff').textContent = totalDiff.toFixed(2);

                            const buttons = [
                                document.getElementById('save-draft'),
                                document.getElementById('save-post')
                            ];

                            // ✅ FIX HERE
                            const isBalanced = Math.abs(totalDiff) < 0.01;
                            const enable = isBalanced && (totalDebit > 0 || totalCredit > 0);

                            buttons.forEach(btn => {
                                if (btn) btn.disabled = !enable;
                            });
                        }
                        // Event listeners
                        document.getElementById('add-line').addEventListener('click', addLine);
                        document.querySelector('#voucher-lines-table tbody').addEventListener('click', removeLine);
                        document.querySelector('#voucher-lines-table tbody').addEventListener('input', handleMutualExclusivity);
                        document.querySelector('#voucher-lines-table tbody').addEventListener('blur', formatDecimal, true);
                        const voucherTypeSelect = document.getElementById('voucher_type');

                        const projectSelect = document.getElementById('project_id');
                        const costCenterSelect = document.getElementById('cost_center_id');

                        function syncContraAccountOptions() {
                            const isContra = voucherTypeSelect?.value === 'contra';

                            document.querySelectorAll('.voucher-account-select').forEach(select => {
                                Array.from(select.options).forEach((option, index) => {
                                    if (index === 0) {
                                        option.hidden = false;
                                        option.disabled = false;
                                        return;
                                    }

                                    const isAllowed = option.dataset.contraAllowed === '1';
                                    const shouldRestrict = isContra && !isAllowed;

                                    option.hidden = shouldRestrict;
                                    option.disabled = shouldRestrict;
                                });

                                const selectedOption = select.options[select.selectedIndex];
                                if (isContra && selectedOption && selectedOption.value && selectedOption.dataset.contraAllowed !== '1') {
                                    select.value = '';
                                }
                            });
                        }

                        const hiddenCostCenterInput = document.getElementById('cost_center_id_hidden');

                        function syncCostCenterWithProject() {

                            const selectedOption = projectSelect.options[projectSelect.selectedIndex];
                            const mappedCostCenterId = selectedOption ? (selectedOption.dataset.costCenterId || '') : '';

                            // Top cost center select + hidden
                            if (costCenterSelect) {
                                costCenterSelect.value = mappedCostCenterId;
                            }
                            if (hiddenCostCenterInput) {
                                hiddenCostCenterInput.value = mappedCostCenterId;
                            }

                            // Apply to voucher lines
                            document.querySelectorAll('#voucher-lines-table tbody tr').forEach(function (row) {

                                const select = row.querySelector('.line-cost-center');
                                const hidden = row.querySelector('.line-cost-center-hidden');

                                if (!select || !hidden) return;

                                if (mappedCostCenterId !== '') {

                                    // Auto select + disable
                                    select.value = mappedCostCenterId;
                                    select.disabled = true;
                                    hidden.value = mappedCostCenterId;

                                } else {

                                    // Project none → enable manual
                                    select.disabled = false;
                                    select.value = '';
                                    hidden.value = '';

                                }

                            });

                        }
                        projectSelect?.addEventListener('change', syncCostCenterWithProject);
                        voucherTypeSelect?.addEventListener('change', syncContraAccountOptions);
                        if (!costCenterSelect.value && projectSelect?.value) {
                            syncCostCenterWithProject();
                        }
                        document.querySelector('#voucher-lines-table tbody')
                            .addEventListener('change', function (e) {

                                if (e.target.classList.contains('line-cost-center')) {

                                    const row = e.target.closest('tr');
                                    const hidden = row.querySelector('.line-cost-center-hidden');

                                    if (hidden) {
                                        hidden.value = e.target.value;
                                    }

                                }

                            });
                        // Initial calculation
                        syncContraAccountOptions();
                        calculateTotals();
                        syncCostCenterWithProject();


                    </script>

                    <a href="{{ $cancelRoute }}" class="btn btn-secondary btn-sm mt-3">Cancel</a>
                </form>
            </div>
        </div>
    </div>
@endsection

@include('accounting.vouchers._prevent_enter_submit')
