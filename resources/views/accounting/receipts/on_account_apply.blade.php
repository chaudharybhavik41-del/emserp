@extends('layouts.erp')

@section('title', 'Apply On-Account')

@section('content')
@php
    $voucher = $voucherLine->voucher;
    $account = $voucherLine->account;
@endphp

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-0">Apply On-Account to Bills</h1>
            <div class="small text-muted">
                Receipt: <strong>{{ $voucher?->voucher_no ?? '-' }}</strong>
                @if($voucher?->voucher_date)
                    <span class="ms-2">Date: {{ \Carbon\Carbon::parse($voucher->voucher_date)->toDateString() }}</span>
                @endif
            </div>
        </div>
        <a href="{{ route('accounting.receipts.on-account.index', ['party_account_id' => $voucherLine->account_id]) }}" class="btn btn-sm btn-outline-secondary">
            Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            <div class="fw-semibold">Please fix the following errors:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="fw-semibold">Client / Debtor Ledger</div>
                    <div class="small">{{ $account?->code }} - {{ $account?->name }}</div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <div class="small text-muted">On-Account available (as-of)</div>
                        <div class="fw-bold">{{ number_format((float) $available, 2) }}</div>
                    </div>

                    @if($lastAllocDate)
                        <div class="small text-muted mt-1">
                            Last allocation date for this receipt: <strong>{{ $lastAllocDate }}</strong>
                        </div>
                    @endif

                    <div class="small text-muted mt-2">
                        Applying On-Account will create bill allocations without creating a new voucher.
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('accounting.receipts.on-account.create', ['voucherLine' => $voucherLine->id]) }}" class="row g-2">
                        <div class="col-12">
                            <label class="form-label form-label-sm">Allocation Date</label>
                            <input type="date" name="allocation_date" class="form-control form-control-sm" value="{{ $allocationDate->toDateString() }}">
                            <div class="small text-muted">Should be on/after receipt date and on/after last allocation date.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Bill Status</label>
                            <select name="status" class="form-select form-select-sm">
                                @php $s = (string) $billStatus; @endphp
                                <option value="posted" @selected($s === 'posted')>Posted</option>
                                <option value="approved" @selected($s === 'approved')>Approved</option>
                                <option value="submitted" @selected($s === 'submitted')>Submitted</option>
                                <option value="draft" @selected($s === 'draft')>Draft</option>
                                <option value="all" @selected($s === 'all')>All</option>
                            </select>
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-sm btn-outline-primary">Reload Bills</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('accounting.receipts.on-account.store', ['voucherLine' => $voucherLine->id]) }}">
                        @csrf

                        <input type="hidden" name="allocation_date" value="{{ $allocationDate->toDateString() }}">
                        <input type="hidden" name="status" value="{{ $billStatus }}">

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">Open Bills</div>
                            <div class="small text-muted">As-of {{ $allocationDate->toFormattedDateString() }}</div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 22%">Bill No</th>
                                        <th style="width: 14%">Bill Date</th>
                                        <th style="width: 16%" class="text-end">Total</th>
                                        <th style="width: 16%" class="text-end">Outstanding</th>
                                        <th style="width: 32%">Apply Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($openBills->isEmpty())
                                        <tr class="text-muted">
                                            <td colspan="5" class="text-center small py-2">No open bills found for this client (for selected status/as-of date).</td>
                                        </tr>
                                    @else
                                        @foreach($openBills as $i => $row)
                                            @php
                                                /** @var \Illuminate\Database\Eloquent\Model $bill */
                                                $bill = $row['bill'];
                                                $out = (float) ($row['outstanding'] ?? 0);
                                                $total = (float) ($row['total'] ?? 0);
                                                $billNo = $row['bill_number'] ?? ($bill->bill_number ?? $bill->invoice_no ?? $bill->id);
                                                $billDate = $row['bill_date'] ?? ($bill->bill_date ?? $bill->invoice_date ?? null);
                                            @endphp
                                            <tr>
                                                <td class="small">{{ $billNo }}</td>
                                                <td class="small">{{ $billDate }}</td>
                                                <td class="small text-end">{{ number_format($total, 2) }}</td>
                                                <td class="small text-end">{{ number_format($out, 2) }}</td>
                                                <td>
                                                    <input type="hidden" name="apply[{{ $i }}][bill_id]" value="{{ $bill->id }}">
                                                    <input type="number"
                                                           step="0.01"
                                                           min="0"
                                                           max="{{ $out }}"
                                                           name="apply[{{ $i }}][amount]"
                                                           class="form-control form-control-sm"
                                                           placeholder="0.00">
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="{{ route('accounting.receipts.on-account.index', ['party_account_id' => $voucherLine->account_id]) }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
                            <button class="btn btn-sm btn-primary">Apply On-Account</button>
                        </div>

                        <div class="small text-muted mt-2">
                            Tip: You can enter partial amounts. Total applied must not exceed On-Account available.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
