@extends('layouts.erp')

@section('title', 'Payment Voucher')

@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Payment Voucher</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.payments.store') }}" data-prevent-enter-submit="1">
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
                        <input type="date" name="voucher_date"
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
                        <label class="form-label form-label-sm">Payee Ledger (Supplier / Expense)</label>
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

                <hr class="mt-3 mb-2">

                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-semibold small">Bill allocations (Purchase Bills)</span>
                        <span class="text-muted small ms-2">(optional)</span>
                    </div>
                    <div class="small text-muted">
                        Select supplier ledger to load open bills.
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
                        <tbody id="purchase-bill-rows">
                            <tr class="text-muted">
                                <td colspan="5" class="text-center small py-2">
                                    Select a supplier ledger to load open purchase bills.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @error('purchase_allocations.*.amount')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror
                @error('purchase_allocations.*.bill_id')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror

                <div class="small text-muted mb-3">
                    You do not have to allocate the full amount. Any unallocated balance will be treated as advance / on-account payment.
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
    const partySelect   = document.getElementById('party_account_id');
    const tbody         = document.getElementById('purchase-bill-rows');
    const oldAlloc      = @json(old('purchase_allocations', []));
    let allocMap        = {};

    if (Array.isArray(oldAlloc)) {
        oldAlloc.forEach(function (row) {
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
            tr.innerHTML = '<td colspan="5" class="text-center small py-2">No open purchase bills for this ledger (or it is not a supplier ledger).</td>';
            tbody.appendChild(tr);
            return;
        }

        bills.forEach(function (bill, idx) {
            const tr = document.createElement('tr');
            const key = bill.id;
            const oldAmount = allocMap[key] || '';

            const total = typeof bill.total_amount === 'number' ? bill.total_amount : parseFloat(bill.total_amount || 0) || 0;
            const outstd = typeof bill.outstanding_amount === 'number' ? bill.outstanding_amount : parseFloat(bill.outstanding_amount || 0) || 0;

            tr.innerHTML = ''
                + '<td class="small">' + (bill.bill_number || '') + '</td>'
                + '<td class="small">' + (bill.bill_date || '') + '</td>'
                + '<td class="small text-end">' + total.toFixed(2) + '</td>'
                + '<td class="small text-end">' + outstd.toFixed(2) + '</td>'
                + '<td>'
                + '  <input type="hidden" name="purchase_allocations[' + idx + '][bill_id]" value="' + bill.id + '">' 
                + '  <input type="number" step="0.01"'
                + '         name="purchase_allocations[' + idx + '][amount]"'
                + '         class="form-control form-control-sm"'
                + '         max="' + outstd + '"'
                + '         value="' + (oldAmount !== '' ? oldAmount : '') + '">' 
                + '</td>';

            tbody.appendChild(tr);
        });
    }

    async function loadBills() {
        const accountId = partySelect.value;

        if (!accountId) {
            tbody.innerHTML = '<tr class="text-muted">'
                + '<td colspan="5" class="text-center small py-2">Select a supplier ledger to load open purchase bills.</td>'
                + '</tr>';
            return;
        }

        try {
            const url  = "{{ route('accounting.api.open-purchase-bills') }}"
                + '?party_account_id=' + encodeURIComponent(accountId);

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
                + '<td colspan="5" class="text-center small py-2">Could not load purchase bills. Please retry or contact admin.</td>'
                + '</tr>';
        }
    }

    if (partySelect) {
        partySelect.addEventListener('change', loadBills);

        if (partySelect.value) {
            loadBills();
        }
    }
});
</script>
@endpush

@include('accounting.vouchers._prevent_enter_submit')
