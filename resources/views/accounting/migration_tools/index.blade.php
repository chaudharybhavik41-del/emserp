@extends('layouts.erp')

@section('title', 'Migration Tools')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Migration Tools</h1>
        <div class="text-muted small">DEV-14 (Phase 1.5)</div>
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> These tools create/update accounting data.
        Please take a <strong>database backup</strong> before running imports and try once on a test copy.
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Opening Balances (Ledger-wise)</h5>
                    <p class="card-text text-muted small mb-3">
                        Import opening balances into Chart of Accounts (opening_balance, dr/cr, date).
                        This is best done <strong>before</strong> posting any vouchers.
                    </p>
                    <a class="btn btn-primary btn-sm" href="{{ route('accounting.migration-tools.opening-balances') }}">
                        Open Tool
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Outstanding AP (Supplier Bills)</h5>
                    <p class="card-text text-muted small mb-3">
                        Import supplier outstanding bills as on cut-over date.
                        Creates <strong>Purchase Bills</strong> + a <strong>Journal Voucher</strong> per bill
                        (offset to Opening Balance Adjustment account).
                    </p>
                    <a class="btn btn-primary btn-sm" href="{{ route('accounting.migration-tools.outstanding-ap') }}">
                        Open Tool
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Outstanding AR (Client Bills)</h5>
                    <p class="card-text text-muted small mb-3">
                        Import client outstanding invoices as on cut-over date.
                        Creates <strong>Client RA Bills</strong> (posted) + a <strong>Journal Voucher</strong> per bill
                        (offset to Opening Balance Adjustment account).
                    </p>
                    <a class="btn btn-primary btn-sm" href="{{ route('accounting.migration-tools.outstanding-ar') }}">
                        Open Tool
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
