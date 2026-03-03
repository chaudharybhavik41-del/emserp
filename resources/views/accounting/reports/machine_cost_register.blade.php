@extends('layouts.erp')

@section('title', 'Machine Cost Register')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Machine Cost Register</h1>
            <div class="small text-muted">Fuel + Spare transaction cost register with monthly summary</div>
        </div>
    </div>

    @if(! $hasFuelIssueTables || ! $hasSpareIssueTables)
        <div class="alert alert-warning">
            Some source tables are missing.
            @if(! $hasFuelIssueTables)
                <div>Fuel Issue data is unavailable.</div>
            @endif
            @if(! $hasSpareIssueTables)
                <div>Machine Spare Issue data is unavailable.</div>
            @endif
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ optional($fromDate)->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ optional($toDate)->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Machine</label>
                    <select name="machine_id" class="form-select form-select-sm">
                        <option value="">-- All Machines --</option>
                        @foreach($machines as $machine)
                            <option value="{{ $machine->id }}" @selected((string) $machineId === (string) $machine->id)>
                                {{ $machine->code ? ($machine->code . ' - ') : '' }}{{ $machine->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All Projects --</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) $projectId === (string) $project->id)>
                                {{ $project->code ? ($project->code . ' - ') : '' }}{{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="{{ route('accounting.reports.machine-cost-register.export', request()->query()) }}"
                       class="btn btn-success btn-sm">Export CSV</a>
                    <a href="{{ route('accounting.reports.machine-cost-register') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="small text-muted">Transactions</div>
                <div class="fs-5 fw-semibold">{{ $grandTransactions }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="small text-muted">Total Qty</div>
                <div class="fs-5 fw-semibold">{{ number_format((float) $grandQty, 3) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="small text-muted">Total Amount</div>
                <div class="fs-5 fw-semibold">{{ number_format((float) $grandAmount, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2">
            <div class="fw-semibold small">Summary by Source</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 35%">Source</th>
                        <th style="width: 20%" class="text-end">Transactions</th>
                        <th style="width: 20%" class="text-end">Qty</th>
                        <th style="width: 25%" class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($summaryBySource as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-end">{{ $row['transactions'] }}</td>
                        <td class="text-end">{{ number_format((float) $row['total_qty'], 3) }}</td>
                        <td class="text-end">{{ number_format((float) $row['total_amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">No data found for selected filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2">
            <div class="fw-semibold small">Monthly Summary</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Fuel Amount</th>
                        <th class="text-end">Spare Amount</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Transactions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($monthlySummary as $row)
                    <tr>
                        <td>{{ $row['month'] }}</td>
                        <td class="text-end">{{ number_format((float) $row['fuel_amount'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $row['spare_amount'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $row['total_amount'], 2) }}</td>
                        <td class="text-end">{{ $row['transactions'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No monthly data found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2">
            <div class="fw-semibold small">Transaction Register</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 9%">Date</th>
                        <th style="width: 10%">Source</th>
                        <th style="width: 12%">Transaction</th>
                        <th style="width: 17%">Machine</th>
                        <th style="width: 17%">Project</th>
                        <th style="width: 10%" class="text-end">Qty</th>
                        <th style="width: 10%" class="text-end">Amount</th>
                        <th style="width: 8%">Acct</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['txn_date'])->format('d-m-Y') }}</td>
                        <td>{{ $row['source_label'] }}</td>
                        <td>
                            @if($row['source'] === 'fuel_issue' && Route::has('fuel-issues.show'))
                                <a href="{{ route('fuel-issues.show', $row['source_id']) }}">{{ $row['txn_no'] }}</a>
                            @elseif($row['source'] === 'spare_issue' && Route::has('store-issues.show'))
                                <a href="{{ route('store-issues.show', $row['source_id']) }}">{{ $row['txn_no'] }}</a>
                            @else
                                {{ $row['txn_no'] }}
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $row['machine_name'] ?: '-' }}</div>
                            <div class="text-muted small">{{ $row['machine_code'] ?: '-' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $row['project_name'] ?: '-' }}</div>
                            <div class="text-muted small">{{ $row['project_code'] ?: '-' }}</div>
                        </td>
                        <td class="text-end">{{ number_format((float) $row['qty'], 3) }}</td>
                        <td class="text-end">{{ number_format((float) $row['amount'], 2) }}</td>
                        <td>{{ $row['accounting_status'] ?: '-' }}</td>
                        <td>{{ $row['remarks'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">No transactions found for selected filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
