@extends('layouts.erp')

@section('title', 'Import Outstanding AP')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Import Outstanding AP (Supplier Bills)</h1>
            <div class="text-muted small">Creates Purchase Bills + Journal Vouchers (CSV)</div>
        </div>
        <a href="{{ route('accounting.migration-tools.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @include('accounting.migration_tools._import_result')

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="text-muted small">
                    Upload supplier outstanding bills as on cut-over date.
                    A <strong>Journal Voucher</strong> is created for each bill to set the supplier ledger balance.
                </div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.migration-tools.outstanding-ap.template') }}">
                    Download Template CSV
                </a>
            </div>

            <form method="POST" action="{{ route('accounting.migration-tools.outstanding-ap.import') }}" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-md-3">
                    <label class="form-label">Cut-over Date</label>
                    <input type="date" name="cutover_date" value="{{ old('cutover_date', now()->toDateString()) }}" class="form-control form-control-sm" required>
                    <div class="form-text">
                        Journal voucher date for opening AP entries.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv,.txt" required>
                    <div class="form-text">
                        Columns: <code>supplier_code, bill_number, bill_date, due_date, amount, remarks</code>
                    </div>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-sm w-100">Import Outstanding AP</button>
                </div>

                <div class="col-12">
                    <div class="alert alert-warning mb-0 small">
                        <strong>Notes:</strong>
                        <ul class="mb-0">
                            <li>This import creates <strong>posted</strong> Purchase Bills and <strong>posted</strong> Journal Vouchers.</li>
                            <li>Do not run twice with the same data (duplicate guard checks supplier + bill number + bill date).</li>
                            <li>Amount should be the <strong>outstanding</strong> amount as on cut-over date.</li>
                            <li>If you also set opening balances for supplier ledgers in COA, you may double-count. Prefer bill-wise opening via this tool.</li>
                        </ul>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
