@extends('layouts.erp')

@section('title', 'Maintenance Cost Analysis')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-cash-coin"></i> Maintenance Cost Analysis</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('maintenance.reports.issued-register') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-text"></i> Issued Register
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('maintenance.reports.cost-analysis') }}" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Contractor</label>
                    <select name="contractor_party_id" class="form-select">
                        <option value="">All Contractors</option>
                        @foreach($contractors as $c)
                            <option value="{{ $c->id }}" {{ (string)request('contractor_party_id') === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>

                <div class="col-md-2 align-self-end">
                    <a href="{{ route('maintenance.reports.cost-analysis') }}" class="btn btn-light w-100">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @php
        $grandTotal = $rows->sum('total_cost_sum');
        $grandDowntime = $rows->sum('downtime_sum');
        $grandJobs = $rows->sum('jobs_count');
    @endphp

    <!-- Summary -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="small opacity-75">Total Cost</div>
                    <div class="fs-3 fw-bold">{{ number_format($grandTotal, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="small opacity-75">Total Jobs</div>
                    <div class="fs-3 fw-bold">{{ number_format($grandJobs) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="small opacity-75">Total Downtime (hrs)</div>
                    <div class="fs-3 fw-bold">{{ number_format($grandDowntime, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Contractor</th>
                            <th>Machine</th>
                            <th class="text-end">Jobs</th>
                            <th class="text-end">Total Cost</th>
                            <th class="text-end">Avg Cost / Job</th>
                            <th class="text-end">Downtime (hrs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $jobs = (int)($row->jobs_count ?? 0);
                                $totalCost = (float)($row->total_cost_sum ?? 0);
                                $avg = $jobs > 0 ? $totalCost / $jobs : 0;
                                $downtime = (float)($row->downtime_sum ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $row->contractor->name ?? ('#' . $row->contractor_party_id) }}</td>
                                <td>
                                    @if($row->machine)
                                        <a href="{{ route('machines.show', $row->machine) }}">{{ $row->machine->code }}</a>
                                        <br>
                                        <small class="text-muted">{{ $row->machine->name }}</small>
                                    @else
                                        #{{ $row->machine_id }}
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($jobs) }}</td>
                                <td class="text-end">{{ number_format($totalCost, 2) }}</td>
                                <td class="text-end">{{ number_format($avg, 2) }}</td>
                                <td class="text-end">{{ number_format($downtime, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No records found for selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
