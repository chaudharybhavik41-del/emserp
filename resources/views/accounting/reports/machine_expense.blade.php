@extends('layouts.erp')

@section('title', 'Machine Expense Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Machine Expense Report</h1>
            <div class="small text-muted">Machine-wise debit expense lines from posted vouchers</div>
        </div>
    </div>

    @if(! $hasMachineDimension)
        <div class="alert alert-warning">
            Machine expense dimension is not available yet. Run latest migrations to add <code>voucher_lines.machine_id</code>.
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
                    <label class="form-label form-label-sm">Project (Optional)</label>
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
                    <a href="{{ route('accounting.reports.machine-expense') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if($hasMachineDimension)
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Lines</div>
                    <div class="fs-5 fw-semibold">{{ $rows->count() }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Expense Heads</div>
                    <div class="fs-5 fw-semibold">{{ $summaryByAccount->count() }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Total Debit</div>
                    <div class="fs-5 fw-semibold">{{ number_format((float) $grandTotal, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header py-2">
                <div class="fw-semibold small">Summary by Expense Head</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 8%">#</th>
                        <th>Account</th>
                        <th style="width: 20%" class="text-end">Debit Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($summaryByAccount as $idx => $row)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $row->account_name }}</div>
                                <div class="text-muted small">{{ $row->account_code ?: '-' }}</div>
                            </td>
                            <td class="text-end">{{ number_format((float) $row->total_debit, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">No data found for selected filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <div class="fw-semibold small">Voucher Lines</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 10%">Date</th>
                        <th style="width: 12%">Voucher</th>
                        <th style="width: 18%">Machine</th>
                        <th style="width: 18%">Account</th>
                        <th>Narration</th>
                        <th style="width: 12%" class="text-end">Debit</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $line)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($line->voucher_date)->format('d-m-Y') }}</td>
                            <td>
                                @if(Route::has('accounting.vouchers.show'))
                                    <a href="{{ route('accounting.vouchers.show', $line->voucher_id) }}" class="text-decoration-none">
                                        {{ $line->voucher_no }}
                                    </a>
                                @else
                                    {{ $line->voucher_no }}
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $line->machine_name ?: ('#' . $line->machine_id) }}</div>
                                <div class="text-muted small">{{ $line->machine_code ?: '-' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $line->account_name }}</div>
                                <div class="text-muted small">{{ $line->account_code ?: '-' }}</div>
                            </td>
                            <td>
                                {{ $line->line_description ?: $line->voucher_narration ?: '-' }}
                            </td>
                            <td class="text-end">{{ number_format((float) $line->debit, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No machine-tagged expense lines found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
