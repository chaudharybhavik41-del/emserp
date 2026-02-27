@extends('layouts.erp')

@section('title', 'Import Opening Balances')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Import Opening Balances</h1>
            <div class="text-muted small">Ledger-wise opening_balance import (CSV)</div>
        </div>
        <a href="{{ route('accounting.migration-tools.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @include('accounting.migration_tools._import_result')

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="text-muted small">
                    Download template, fill your data in Excel, then <strong>Save As CSV</strong>.
                </div>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.migration-tools.opening-balances.template') }}">
                    Download Template CSV
                </a>
            </div>

            <form method="POST" action="{{ route('accounting.migration-tools.opening-balances.import') }}" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-md-3">
                    <label class="form-label">Opening Balance Date (Default)</label>
                    <input type="date" name="as_on_date" value="{{ old('as_on_date', now()->toDateString()) }}" class="form-control form-control-sm" required>
                    <div class="form-text">
                        Used when a row does not provide <code>opening_date</code>.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv,.txt" required>
                    <div class="form-text">
                        Columns: <code>account_code, opening_balance, dr_cr, opening_date</code>
                    </div>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-sm w-100">Import Opening Balances</button>
                </div>

                <div class="col-12">
                    <div class="alert alert-secondary mb-0 small">
                        <strong>Rules:</strong>
                        <ul class="mb-0">
                            <li>If <code>dr_cr</code> is blank, a signed balance is allowed (negative = CR, positive = DR).</li>
                            <li>If any vouchers already exist for an account, opening balance cannot be changed (use a Journal Voucher adjustment instead).</li>
                            <li>Use YYYY-MM-DD dates to avoid ambiguity.</li>
                        </ul>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
