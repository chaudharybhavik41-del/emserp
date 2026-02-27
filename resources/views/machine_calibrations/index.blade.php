@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-clipboard-check"></i> Machine Calibrations</h2>
        <div>
            <a href="{{ route('machine-calibrations.dashboard') }}" class="btn btn-secondary">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            @can('machinery.calibration.create')
            <a href="{{ route('machine-calibrations.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Record Calibration
            </a>
            @endcan
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('machine-calibrations.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control" placeholder="Search cal #, machine, agency..." 
                           value="{{ request('q') }}">
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
                    <select name="result" class="form-select">
                        <option value="">All Results</option>
                        <option value="pass" {{ request('result') == 'pass' ? 'selected' : '' }}>Pass</option>
                        <option value="pass_with_adjustment" {{ request('result') == 'pass_with_adjustment' ? 'selected' : '' }}>Pass with Adjustment</option>
                        <option value="fail" {{ request('result') == 'fail' ? 'selected' : '' }}>Fail</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Calibrations Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Cal #</th>
                            <th>Machine</th>
                            <th>Calibration Date</th>
                            <th>Next Due</th>
                            <th>Agency</th>
                            <th>Certificate</th>
                            <th>Result</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calibrations as $calibration)
                        <tr class="{{ $calibration->isOverdue() ? 'table-warning' : '' }}">
                            <td>
                                <a href="{{ route('machine-calibrations.show', $calibration) }}">
                                    <strong>{{ $calibration->calibration_number }}</strong>
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('machines.show', $calibration->machine) }}">
                                    {{ $calibration->machine->code }}
                                </a>
                                <br><small class="text-muted">{{ $calibration->machine->name }}</small>
                            </td>
                            <td>{{ $calibration->calibration_date->format('d-M-Y') }}</td>
                            <td>
                                {{ $calibration->next_due_date->format('d-M-Y') }}
                                @if($calibration->isDueSoon())
                                    <br><span class="badge bg-warning text-dark">Due Soon</span>
                                @elseif($calibration->isOverdue())
                                    <br><span class="badge bg-danger">Overdue</span>
                                @endif
                            </td>
                            <td>{{ $calibration->calibration_agency }}</td>
                            <td>
                                @if($calibration->hasCertificate())
                                    <a href="{{ $calibration->getCertificateUrl() }}" target="_blank" 
                                       class="btn btn-sm btn-outline-primary" title="View Certificate">
                                        <i class="bi bi-file-pdf"></i>
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $calibration->getResultBadgeClass() }}">
                                    {{ ucfirst(str_replace('_', ' ', $calibration->result)) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $calibration->getStatusBadgeClass() }}">
                                    {{ ucfirst($calibration->status) }}
                                </span>
                            </td>
                            <td>
                                @if($calibration->calibration_cost > 0)
                                    ₹{{ number_format($calibration->calibration_cost, 2) }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('machine-calibrations.show', $calibration) }}" 
                                       class="btn btn-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @can('machinery.calibration.update')
                                    <a href="{{ route('machine-calibrations.edit', $calibration) }}" 
                                       class="btn btn-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan
                                    @can('machinery.calibration.delete')
                                    <form action="{{ route('machine-calibrations.destroy', $calibration) }}" 
                                          method="POST" 
                                          onsubmit="return confirm('Delete this calibration record?');"
                                          class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted">No calibration records found.</p>
                                @can('machinery.calibration.create')
                                <a href="{{ route('machine-calibrations.create') }}" class="btn btn-primary">
                                    Record First Calibration
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
                {{ $calibrations->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
