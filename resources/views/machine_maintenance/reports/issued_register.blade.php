@extends('layouts.erp')

@section('title', 'Issued Machine Register')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-file-earmark-text"></i> Issued Machine Register</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('maintenance.reports.cost-analysis') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-cash-coin"></i> Cost Analysis
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('maintenance.reports.issued-register') }}" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        @foreach(['active','returned','extended','damaged','lost'] as $st)
                            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>
                                {{ ucfirst($st) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="assignment_type" class="form-select">
                        <option value="">All</option>
                        <option value="contractor" {{ request('assignment_type') == 'contractor' ? 'selected' : '' }}>Contractor</option>
                        <option value="company_worker" {{ request('assignment_type') == 'company_worker' ? 'selected' : '' }}>Company Worker</option>
                    </select>
                </div>

                <div class="col-md-3">
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

                <div class="col-md-1">
                    <label class="form-label">Worker ID</label>
                    <input type="number" name="worker_user_id" class="form-control" value="{{ request('worker_user_id') }}" placeholder="ID">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100 mt-4">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <div class="col-md-2">
                    <a href="{{ route('maintenance.reports.issued-register') }}" class="btn btn-light w-100 mt-4">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Assigned Date</th>
                            <th>Assignment #</th>
                            <th>Machine</th>
                            <th>Assigned To</th>
                            <th>Type</th>
                            <th>Project</th>
                            <th>Expected Return</th>
                            <th>Actual Return</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments as $a)
                            <tr class="{{ $a->isOverdue() ? 'table-danger' : '' }}">
                                <td>{{ optional($a->assigned_date)->format('d-M-Y') }}</td>
                                <td>
                                    <a href="{{ route('machine-assignments.show', $a) }}">
                                        <strong>{{ $a->assignment_number }}</strong>
                                    </a>
                                </td>
                                <td>
                                    @if($a->machine)
                                        <a href="{{ route('machines.show', $a->machine) }}">{{ $a->machine->code }}</a>
                                        <br><small class="text-muted">{{ $a->machine->name }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $a->getAssignedToName() }}</td>
                                <td>
                                    <span class="badge bg-{{ $a->assignment_type == 'contractor' ? 'info' : 'primary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $a->assignment_type)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($a->project)
                                        {{ $a->project->name }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($a->expected_return_date)
                                        {{ $a->expected_return_date->format('d-M-Y') }}
                                        @if($a->isOverdue())
                                            <br><span class="badge bg-danger">OVERDUE</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($a->actual_return_date)
                                        {{ $a->actual_return_date->format('d-M-Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ method_exists($a, 'getStatusBadgeClass') ? $a->getStatusBadgeClass() : 'secondary' }}">
                                        {{ ucfirst($a->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No records found for selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $assignments->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
