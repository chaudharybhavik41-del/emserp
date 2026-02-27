@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-arrow-left-right"></i> Machine Assignments</h2>
        @can('machinery.assignment.create')
        <a href="{{ route('machine-assignments.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Issue Machine
        </a>
        @endcan
    </div>

    <!-- Search & Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('machine-assignments.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control" placeholder="Search assignment #, machine..." 
                           value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                        <option value="extended" {{ request('status') == 'extended' ? 'selected' : '' }}>Extended</option>
                        <option value="damaged" {{ request('status') == 'damaged' ? 'selected' : '' }}>Damaged</option>
                        <option value="lost" {{ request('status') == 'lost' ? 'selected' : '' }}>Lost</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assignment_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="contractor" {{ request('assignment_type') == 'contractor' ? 'selected' : '' }}>Contractor</option>
                        <option value="company_worker" {{ request('assignment_type') == 'company_worker' ? 'selected' : '' }}>Company Worker</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="machine_id" class="form-select">
                        <option value="">All Machines</option>
                        @foreach($machines as $machine)
                        <option value="{{ $machine->id }}" {{ request('machine_id') == $machine->id ? 'selected' : '' }}>
                            {{ $machine->code }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-2">
                        <input type="checkbox" name="overdue_only" value="1" class="form-check-input" id="overdue_only"
                               {{ request('overdue_only') ? 'checked' : '' }}>
                        <label class="form-check-label" for="overdue_only">Overdue Only</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Active Assignments</h5>
                    <h2>{{ $stats['active'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Overdue</h5>
                    <h2>{{ $stats['overdue'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Returned (Month)</h5>
                    <h2>{{ $stats['returned_month'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Total Assignments</h5>
                    <h2>{{ $stats['total'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignments Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Assignment #</th>
                            <th>Machine</th>
                            <th>Assigned To</th>
                            <th>Type</th>
                            <th>Project</th>
                            <th>Assigned Date</th>
                            <th>Expected Return</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments as $assignment)
                        <tr class="{{ $assignment->isOverdue() ? 'table-danger' : '' }}">
                            <td>
                                <a href="{{ route('machine-assignments.show', $assignment) }}">
                                    <strong>{{ $assignment->assignment_number }}</strong>
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('machines.show', $assignment->machine) }}">
                                    {{ $assignment->machine->code }}
                                </a>
                                <br><small class="text-muted">{{ $assignment->machine->name }}</small>
                            </td>
                            <td>
                                {{ $assignment->getAssignedToName() }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $assignment->assignment_type == 'contractor' ? 'info' : 'primary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $assignment->assignment_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($assignment->project)
                                    {{ $assignment->project->name }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $assignment->assigned_date->format('d-M-Y') }}</td>
                            <td>
                                @if($assignment->expected_return_date)
                                    {{ $assignment->expected_return_date->format('d-M-Y') }}
                                    @if($assignment->isOverdue())
                                        <br><span class="badge bg-danger">OVERDUE</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $assignment->getStatusBadgeClass() }}">
                                    {{ ucfirst($assignment->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('machine-assignments.show', $assignment) }}" 
                                       class="btn btn-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($assignment->isActive())
                                        @can('machinery.assignment.return')
                                        <a href="{{ route('machine-assignments.return-form', $assignment) }}" 
                                           class="btn btn-success" title="Return">
                                            <i class="bi bi-arrow-return-left"></i>
                                        </a>
                                        @endcan
                                        @can('machinery.assignment.extend')
                                        <a href="{{ route('machine-assignments.extend-form', $assignment) }}" 
                                           class="btn btn-warning" title="Extend">
                                            <i class="bi bi-calendar-plus"></i>
                                        </a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted">No assignments found.</p>
                                @can('machinery.assignment.create')
                                <a href="{{ route('machine-assignments.create') }}" class="btn btn-primary">
                                    Issue First Machine
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-3">
                {{ $assignments->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
