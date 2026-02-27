@extends('layouts.erp')

@section('title', 'Import Outstanding AR')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Import Outstanding AR (Client Bills)</h1>
            <div class="text-muted small">Creates Client RA Bills + Journal Vouchers (CSV)</div>
        </div>
        <a href="{{ route('accounting.migration-tools.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @include('accounting.migration_tools._import_result')

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="text-muted small">
                    Upload client outstanding invoices as on cut-over date.
                    A <strong>Journal Voucher</strong> is created for each bill to set the client ledger balance.
                </div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.migration-tools.outstanding-ar.template') }}">
                    Download Template CSV
                </a>
            </div>

            <form method="POST" action="{{ route('accounting.migration-tools.outstanding-ar.import') }}" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-md-3">
                    <label class="form-label">Cut-over Date</label>
                    <input type="date" name="cutover_date" value="{{ old('cutover_date', now()->toDateString()) }}" class="form-control form-control-sm" required>
                    <div class="form-text">
                        Journal voucher date for opening AR entries.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv,.txt" required>
                    <div class="form-text">
                        Columns: <code>client_code, project_code, invoice_number, bill_date, due_date, amount, remarks</code>
                    </div>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-sm w-100">Import Outstanding AR</button>
                </div>

                <div class="col-12">
                    <div class="alert alert-warning mb-0 small">
                        <strong>Notes:</strong>
                        <ul class="mb-0">
                            <li>This import creates <strong>posted</strong> Client RA Bills and <strong>posted</strong> Journal Vouchers.</li>
                            <li>Project is required (Client RA Bills are project-linked by design).</li>
                            <li>Duplicate guard checks <code>invoice_number</code> (if provided).</li>
                            <li>Amount should be the <strong>outstanding</strong> amount as on cut-over date.</li>
                            <li>If you also set opening balances for client ledgers in COA, you may double-count. Prefer bill-wise opening via this tool.</li>
                        </ul>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
