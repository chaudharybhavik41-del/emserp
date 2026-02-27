@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-clipboard-check"></i> Calibration Dashboard</h2>
        <div>
            <a href="{{ route('machine-calibrations.index') }}" class="btn btn-secondary">
                <i class="bi bi-list"></i> All Calibrations
            </a>
            @can('machinery.calibration.create')
            <a href="{{ route('machine-calibrations.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Record Calibration
            </a>
            @endcan
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-tools"></i> Requiring Calibration</h6>
                    <h2 class="mb-0">{{ $stats['total_requiring'] }}</h2>
                    <small>Total machines</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-exclamation-triangle"></i> Overdue</h6>
                    <h2 class="mb-0">{{ $stats['overdue_count'] }}</h2>
                    <small>Past due date</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-calendar-event"></i> Due Soon</h6>
                    <h2 class="mb-0">{{ $stats['due_soon_count'] }}</h2>
                    <small>Within {{ config('machinery.calibration_alert_days', 15) }} days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="mb-2"><i class="bi bi-check-circle"></i> This Month</h6>
                    <h2 class="mb-0">{{ $stats['completed_this_month'] }}</h2>
                    <small>Calibrations completed</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Overdue Calibrations -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Overdue Calibrations</h5>
                </div>
                <div class="card-body p-0">
                    @if($overdue->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Last Calibrated</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($overdue as $machine)
                                <tr>
                                    <td>
                                        <a href="{{ route('machines.show', $machine) }}">
                                            <strong>{{ $machine->code }}</strong>
                                        </a>
                                        <br><small class="text-muted">{{ $machine->name }}</small>
                                    </td>
                                    <td>
                                        @if($machine->last_calibration_date)
                                            {{ $machine->last_calibration_date->format('d-M-Y') }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-danger">
                                            {{ $machine->next_calibration_due_date->format('d-M-Y') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">
                                            {{ abs($machine->next_calibration_due_date->diffInDays(now())) }} days
                                        </span>
                                    </td>
                                    <td>
                                        @can('machinery.calibration.create')
                                        <a href="{{ route('machine-calibrations.create', ['machine_id' => $machine->id]) }}" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus"></i> Record
                                        </a>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-1"></i>
                        <p class="mb-0">No overdue calibrations! 🎉</p>
                    </div>
                    @endif
                </div>
                @if($overdue->count() > 10)
                <div class="card-footer">
                    <a href="{{ route('machine-calibrations.index', ['status' => 'overdue']) }}" class="btn btn-sm btn-outline-danger">
                        View All Overdue ({{ $overdue->count() }})
                    </a>
                </div>
                @endif
            </div>
        </div>

        <!-- Due Soon -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Due Soon (Next 15 Days)</h5>
                </div>
                <div class="card-body p-0">
                    @if($dueSoon->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Last Calibrated</th>
                                    <th>Due Date</th>
                                    <th>Days Left</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dueSoon as $machine)
                                <tr>
                                    <td>
                                        <a href="{{ route('machines.show', $machine) }}">
                                            <strong>{{ $machine->code }}</strong>
                                        </a>
                                        <br><small class="text-muted">{{ $machine->name }}</small>
                                    </td>
                                    <td>
                                        @if($machine->last_calibration_date)
                                            {{ $machine->last_calibration_date->format('d-M-Y') }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $machine->next_calibration_due_date->format('d-M-Y') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            {{ $machine->next_calibration_due_date->diffInDays(now()) }} days
                                        </span>
                                    </td>
                                    <td>
                                        @can('machinery.calibration.create')
                                        <a href="{{ route('machine-calibrations.create', ['machine_id' => $machine->id]) }}" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus"></i> Record
                                        </a>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-check fs-1"></i>
                        <p class="mb-0">No calibrations due in the next 15 days</p>
                    </div>
                    @endif
                </div>
                @if($dueSoon->count() > 10)
                <div class="card-footer">
                    <a href="{{ route('machine-calibrations.index', ['status' => 'due_soon']) }}" class="btn btn-sm btn-outline-warning">
                        View All Due Soon ({{ $dueSoon->count() }})
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Calibrations -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Calibrations</h5>
        </div>
        <div class="card-body p-0">
            @if($recent->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Cal #</th>
                            <th>Machine</th>
                            <th>Calibration Date</th>
                            <th>Next Due</th>
                            <th>Agency</th>
                            <th>Result</th>
                            <th>Certificate</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $calibration)
                        <tr>
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
                            <td>{{ $calibration->next_due_date->format('d-M-Y') }}</td>
                            <td>{{ $calibration->calibration_agency }}</td>
                            <td>
                                <span class="badge bg-{{ $calibration->getResultBadgeClass() }}">
                                    {{ ucfirst(str_replace('_', ' ', $calibration->result)) }}
                                </span>
                            </td>
                            <td>
                                @if($calibration->hasCertificate())
                                    <a href="{{ $calibration->getCertificateUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-pdf"></i> View
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('machine-calibrations.show', $calibration) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mb-0">No recent calibrations</p>
            </div>
            @endif
        </div>
        @if($recent->count() > 0)
        <div class="card-footer">
            <a href="{{ route('machine-calibrations.index') }}" class="btn btn-sm btn-outline-secondary">
                View All Calibrations
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
