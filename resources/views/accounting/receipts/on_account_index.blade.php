@extends('layouts.erp')

@section('title', 'Apply On-Account')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Apply On-Account (Advance) to Bills</h1>
        <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-sm btn-outline-secondary">Back to Vouchers</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.receipts.on-account.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label form-label-sm">Client / Debtor Ledger</label>
                    <select name="party_account_id" class="form-select form-select-sm">
                        <option value="">-- Select --</option>
                        @foreach($debtorAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected(($selectedAccount?->id ?? null) === $acc->id)>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate->toDateString() }}">
                </div>

                <div class="col-md-2 d-grid">
                    <button class="btn btn-sm btn-primary">Load</button>
                </div>
            </form>

            <div class="small text-muted mt-2">
                This screen shows receipt entries where some amount is still <strong>On-Account</strong> (unallocated). You can apply that advance to open client bills.
            </div>
        </div>
    </div>

    @if($selectedAccount)
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold">{{ $selectedAccount->code }} - {{ $selectedAccount->name }}</div>
                        <div class="small text-muted">On-Account lines as of {{ $asOfDate->toFormattedDateString() }}</div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Total On-Account</div>
                        <div class="fw-bold">{{ number_format((float) $totalOnAccount, 2) }}</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 18%;">Receipt Voucher No</th>
                                <th style="width: 14%;">Receipt Date</th>
                                <th style="width: 14%;" class="text-end">On-Account</th>
                                <th style="width: 54%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($onAccountLines->isEmpty())
                                <tr class="text-muted">
                                    <td colspan="4" class="text-center small py-2">No on-account balance found for this ledger.</td>
                                </tr>
                            @else
                                @foreach($onAccountLines as $row)
                                    @php
                                        /** @var \App\Models\Accounting\VoucherLine $vline */
                                        $vline = $row['voucher_line'];
                                        $vno = $row['voucher_no'] ?? '-';
                                        $vdate = $row['voucher_date'] ?? '';
                                        $amt = (float) ($row['on_account'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="small">{{ $vno }}</td>
                                        <td class="small">{{ $vdate }}</td>
                                        <td class="small text-end">{{ number_format($amt, 2) }}</td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="{{ route('accounting.receipts.on-account.create', ['voucherLine' => $vline->id, 'allocation_date' => $asOfDate->toDateString(), 'status' => 'posted']) }}">
                                                Apply to Bills
                                            </a>
                                            <span class="small text-muted ms-2">(Uses this receipt's on-account balance)</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="small text-muted mt-2">
                    Note: allocation date must be on/after the last allocation date for the same receipt (to avoid back-dated re-allocation).
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
