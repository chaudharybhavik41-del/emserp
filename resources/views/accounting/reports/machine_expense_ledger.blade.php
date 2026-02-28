@extends('layouts.erp')
@section('title', 'Machine Expense Ledger')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Machine Expense Ledger</h1>
            <div class="small text-muted">Expense voucher lines tagged to machines</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Machine</label>
                    <select name="machine_id" class="form-select">
                        <option value="">All Machines</option>
                        @foreach($machines as $machine)
                            <option value="{{ $machine->id }}" @selected($machineId === (int) $machine->id)>
                                {{ $machine->asset_code }} - {{ $machine->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($projectId === (int) $project->id)>
                                {{ $project->code }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary btn-sm">Apply</button>
                    <a href="{{ route('accounting.reports.machine-expense-ledger') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-2 fw-semibold">Voucher Rows</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Voucher</th>
                                    <th>Account</th>
                                    <th>Machine</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td>{{ optional($row->voucher?->voucher_date)->format('d-m-Y') }}</td>
                                        <td>{{ $row->voucher?->voucher_no }}</td>
                                        <td>{{ $row->account?->name ?? ('#' . $row->account_id) }}</td>
                                        <td>{{ $row->machine ? ($row->machine->asset_code . ' - ' . $row->machine->name) : '—' }}</td>
                                        <td>{{ $row->description ?: '—' }}</td>
                                        <td class="text-end">{{ number_format((float) $row->debit, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $row->credit, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">No tagged machine expenses found in selected filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-2 fw-semibold">Totals by Expense Ledger</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ledger</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupedTotals as $ledger => $totals)
                                <tr>
                                    <td>{{ $ledger }}</td>
                                    <td class="text-end">{{ number_format((float) $totals['net'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">No totals available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
