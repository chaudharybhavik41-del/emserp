@extends('layouts.erp')

@section('title', 'Edit Voucher')

@section('content')
                    <div class="container-fluid">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h1 class="h4 mb-0">Edit Voucher</h1>
                            <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="btn btn-outline-secondary btn-sm">
                                Back
                            </a>
                        </div>

                        <div class="alert alert-info small">
                            This voucher is in <strong>Draft</strong> status. Once you <strong>Post</strong> it, editing will be blocked. Use <strong>Reverse</strong> to correct posted vouchers.
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="{{ route('accounting.vouchers.update', $voucher) }}" data-prevent-enter-submit="1">
                                    @csrf
                                    @method('PUT')

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label form-label-sm">Voucher No.</label>
                                            <input type="text" name="voucher_no" class="form-control form-control-sm" value="{{ old('voucher_no', $voucher->voucher_no) }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label form-label-sm">Type</label>
                                            <select name="voucher_type" class="form-select form-select-sm">
                                                @foreach($voucherTypes as $key => $label)
                                                    <option value="{{ $key }}" @selected(old('voucher_type', $voucher->voucher_type) === $key)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label form-label-sm">Date</label>
                                            <input type="date" name="voucher_date" class="form-control form-control-sm" value="{{ old('voucher_date', optional($voucher->voucher_date)->toDateString()) }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label form-label-sm">Exchange Rate</label>
                                            <input type="number" step="0.000001" name="exchange_rate" class="form-control form-control-sm" value="{{ old('exchange_rate', $voucher->exchange_rate ?? 1) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label form-label-sm">Project</label>
                                            <select name="project_id" class="form-select form-select-sm">
                                                <option value="">-- none --</option>
                                                @foreach($projects as $p)
                                                    <option value="{{ $p->id }}" @selected((string) old('project_id', $voucher->project_id) === (string) $p->id)>
                                                        {{ $p->code ? ($p->code . ' - ') : '' }}{{ $p->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label form-label-sm">Cost Center</label>
                                            <select name="cost_center_id" class="form-select form-select-sm">
                                                <option value="">-- none --</option>
                                                @foreach($costCenters as $cc)
                                                    <option value="{{ $cc->id }}" @selected((string) old('cost_center_id', $voucher->cost_center_id) === (string) $cc->id)>{{ $cc->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label form-label-sm">Reference</label>
                                            <input type="text" name="reference" class="form-control form-control-sm" value="{{ old('reference', $voucher->reference) }}">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label form-label-sm">Narration</label>
                                        <textarea name="narration" class="form-control form-control-sm" rows="2">{{ old('narration', $voucher->narration) }}</textarea>
                                    </div>

                                    <hr>

                                    <h6>Lines</h6>

                        @php
    $existingLines = $voucher->lines->sortBy('line_no')->values();
    $rowCount = max(6, $existingLines->count());
                        @endphp

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5>Voucher Lines</h5>
                            <button type="button" id="add-line" class="btn btn-sm btn-primary">Add Line</button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm" id="voucher-lines-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account</th>
                                        <th>Cost Center</th>
                                        <th>Description</th>
                                        <th>Debit</th>
                                        <th>Credit</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($i = 0; $i < $rowCount; $i++)
                                        @php $ln = $existingLines->get($i); @endphp
                                        <tr>
                                            <td class="row-number">{{ $i + 1 }}</td>
                                            <td>
                                                <select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm">
                                                    <option value="">-- select --</option>
                                                    @foreach($accounts as $account)
                                                        <option value="{{ $account->id }}" @selected((string) old("lines.$i.account_id", $ln?->account_id) === (string) $account->id)>
                                                            {{ $account->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="lines[{{ $i }}][cost_center_id]" class="form-select form-select-sm">
                                                    <option value="">-- none --</option>
                                                    @foreach($costCenters as $cc)
                                                        <option value="{{ $cc->id }}" @selected((string) old("lines.$i.cost_center_id", $ln?->cost_center_id) === (string) $cc->id)>
                                                            {{ $cc->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="lines[{{ $i }}][description]" class="form-control form-control-sm"
                                                    value="{{ old("lines.$i.description", $ln?->description) }}">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="lines[{{ $i }}][debit]"
                                                    class="form-control form-control-sm debit" value="{{ old("lines.$i.debit", $ln?->debit) }}">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="lines[{{ $i }}][credit]"
                                                    class="form-control form-control-sm credit" value="{{ old("lines.$i.credit", $ln?->credit) }}">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger remove-line">Remove</button>
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total</th>
                                        <th id="total-debit">0.00</th>
                                        <th id="total-credit">0.00</th>
                                        <th id="total-diff">0.00</th>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <span id="balance-status" class="fw-bold text-danger">Voucher not matched balanced</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-3">
                            <button type="submit" id="save-draft" class="btn btn-primary btn-sm" disabled>Save Draft</button>

                            @can('accounting.vouchers.update')
                                <button type="submit" id="save-post" name="post_now" value="1" class="btn btn-success btn-sm" disabled>
                                    Save & Post
                                </button>
                            @endcan
                        </div><script>
                            document.addEventListener('DOMContentLoaded', () => {
                                let lineIndex = {{ $rowCount }}; // next row index

                                const table = document.getElementById('voucher-lines-table');
                                const tbody = table.querySelector('tbody');
                                const addLineBtn = document.getElementById('add-line');
                                const saveDraftBtn = document.getElementById('save-draft');
                                const savePostBtn = document.getElementById('save-post');
                                const balanceStatus = document.getElementById('balance-status');

                                // Hide any blank rows initially
                                tbody.querySelectorAll('tr').forEach(row => {
                                    const debit = row.querySelector('.debit').value;
                                    const credit = row.querySelector('.credit').value;
                                    if (!debit && !credit && !row.querySelector('select').value) {
                                        row.style.display = 'none';
                                    }
                                });

                                // Update row numbers and input names
                                function updateRows() {
                                    tbody.querySelectorAll('tr').forEach((row, index) => {
                                        row.querySelector('.row-number').textContent = index + 1;
                                        row.querySelectorAll('select, input').forEach(input => {
                                            input.name = input.name.replace(/lines\[\d+\]/, `lines[${index}]`);
                                        });
                                        row.style.display = 'table-row'; // show rows
                                    });
                                    calculateTotals();
                                }

                                // Add new row
                                addLineBtn.addEventListener('click', () => {
                                    const newRow = document.createElement('tr');
                                    newRow.innerHTML = `
                                    <td class="row-number"></td>
                                    <td>
                                        <select name="lines[${lineIndex}][account_id]" class="form-select form-select-sm">
                                            <option value="">-- select --</option>
                                            @foreach($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="lines[${lineIndex}][cost_center_id]" class="form-select form-select-sm">
                                            <option value="">-- none --</option>
                                            @foreach($costCenters as $cc)
                                                <option value="{{ $cc->id }}">{{ $cc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="lines[${lineIndex}][description]" class="form-control form-control-sm"></td>
                                    <td><input type="number" step="0.01" name="lines[${lineIndex}][debit]" class="form-control form-control-sm debit"></td>
                                    <td><input type="number" step="0.01" name="lines[${lineIndex}][credit]" class="form-control form-control-sm credit"></td>
                                    <td><button type="button" class="btn btn-sm btn-danger remove-line">Remove</button></td>
                                `;
                                    tbody.appendChild(newRow);
                                    lineIndex++;
                                    updateRows();
                                });

                                // Remove row
                                tbody.addEventListener('click', e => {
                                    if (e.target.classList.contains('remove-line')) {
                                        e.target.closest('tr').remove();
                                        updateRows();
                                    }
                                });

                                // Debit/Credit mutual exclusivity + totals
                                tbody.addEventListener('input', e => {
                                    const row = e.target.closest('tr');
                                    const debit = row.querySelector('.debit');
                                    const credit = row.querySelector('.credit');

                                    // Mutual exclusivity
                                    if (e.target.classList.contains('debit')) {
                                        credit.disabled = debit.value && parseFloat(debit.value) > 0;
                                    }
                                    if (e.target.classList.contains('credit')) {
                                        debit.disabled = credit.value && parseFloat(credit.value) > 0;
                                    }

                                    calculateTotals();
                                });

                                // Format decimals on blur
                                tbody.addEventListener('blur', e => {
                                    if (e.target.classList.contains('debit') || e.target.classList.contains('credit')) {
                                        let val = parseFloat(e.target.value) || 0;
                                        e.target.value = val.toFixed(2);
                                        calculateTotals();
                                    }
                                }, true);

                                // Calculate totals, difference, enable buttons, update status
                                function calculateTotals() {
                                    let totalDebit = 0;
                                    let totalCredit = 0;

                                    tbody.querySelectorAll('tr').forEach(row => {
                                        const debitInput = row.querySelector('.debit');
                                        const creditInput = row.querySelector('.credit');

                                        const debit = parseFloat(debitInput?.value) || 0;
                                        const credit = parseFloat(creditInput?.value) || 0;

                                        // Mutual exclusivity fix for initially loaded rows
                                        debitInput.disabled = credit > 0;
                                        creditInput.disabled = debit > 0;

                                        totalDebit += debit;
                                        totalCredit += credit;
                                    });

                                    const totalDiff = totalDebit - totalCredit;

                                    document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
                                    document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
                                    document.getElementById('total-diff').textContent = totalDiff.toFixed(2);

                                    const enable = totalDiff === 0 && (totalDebit > 0 || totalCredit > 0);
                                    if (saveDraftBtn) saveDraftBtn.disabled = !enable;
                                    if (savePostBtn) savePostBtn.disabled = !enable;

                                    balanceStatus.textContent = enable ? "Voucher matched balanced ✅" : "Voucher not matched balanced ❌";
                                    balanceStatus.classList.toggle('text-success', enable);
                                    balanceStatus.classList.toggle('text-danger', !enable);
                                }

                                // Initial calculation
                                calculateTotals();
                            });
                        </script>
                                    <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="btn btn-outline-secondary btn-sm mt-3">Cancel</a>
                                </form>
                            </div>
                        </div>
                    </div>
@endsection

@include('accounting.vouchers._prevent_enter_submit')
