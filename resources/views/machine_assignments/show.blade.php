@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-file-text"></i> Assignment: {{ $assignment->assignment_number }}</h2>
        <div>
            @if($assignment->isActive())
                @can('machinery.assignment.return')
                <a href="{{ route('machine-assignments.return-form', $assignment) }}" class="btn btn-success">
                    <i class="bi bi-arrow-return-left"></i> Return Machine
                </a>
                @endcan
                @can('machinery.assignment.extend')
                <a href="{{ route('machine-assignments.extend-form', $assignment) }}" class="btn btn-warning">
                    <i class="bi bi-calendar-plus"></i> Extend Assignment
                </a>
                @endcan
            @endif
            <a href="{{ route('machine-assignments.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-md-8">
            <!-- Assignment Details -->
            <div class="card mb-3">
                <div class="card-header"><strong>Assignment Details</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Assignment #:</th>
                                    <td><strong>{{ $assignment->assignment_number }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Machine:</th>
                                    <td>
                                        <a href="{{ route('machines.show', $assignment->machine) }}">
                                            {{ $assignment->machine->code }}
                                        </a>
                                        <br>{{ $assignment->machine->name }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Assignment Type:</th>
                                    <td>
                                        <span class="badge bg-{{ $assignment->assignment_type == 'contractor' ? 'info' : 'primary' }}">
                                            {{ ucfirst(str_replace('_', ' ', $assignment->assignment_type)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Assigned To:</th>
                                    <td><strong>{{ $assignment->getAssignedToName() }}</strong></td>
                                </tr>
                                @if($assignment->project)
                                <tr>
                                    <th>Project:</th>
                                    <td>{{ $assignment->project->name }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Assigned Date:</th>
                                    <td>{{ $assignment->assigned_date->format('d-M-Y') }}</td>
                                </tr>
                                <tr>
                                    <th>Expected Return:</th>
                                    <td>
                                        @if($assignment->expected_return_date)
                                            {{ $assignment->expected_return_date->format('d-M-Y') }}
                                            @if($assignment->isOverdue())
                                                <br><span class="badge bg-danger">OVERDUE by {{ $assignment->getOverdueDays() }} days</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Actual Return:</th>
                                    <td>
                                        @if($assignment->actual_return_date)
                                            {{ $assignment->actual_return_date->format('d-M-Y') }}
                                        @else
                                            <span class="text-muted">Not returned yet</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Duration:</th>
                                    <td>{{ $assignment->getDurationDays() }} days</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-{{ $assignment->getStatusBadgeClass() }} fs-6">
                                            {{ ucfirst($assignment->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Condition & Meter Reading -->
            <div class="card mb-3">
                <div class="card-header"><strong>Machine Condition & Meter Reading</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>At Issue</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Condition:</th>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ ucfirst(str_replace('_', ' ', $assignment->condition_at_issue)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Meter Reading:</th>
                                    <td>
                                        @if($assignment->meter_reading_at_issue)
                                            {{ number_format($assignment->meter_reading_at_issue, 2) }} hrs
                                        @else
                                            <span class="text-muted">Not recorded</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($assignment->issue_remarks)
                                <tr>
                                    <th>Remarks:</th>
                                    <td>{{ $assignment->issue_remarks }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>At Return</h6>
                            @if($assignment->isReturned())
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Return Action:</th>
                                    <td>
                                        @if(($assignment->return_disposition ?? 'returned') === 'scrapped')
                                            <span class="badge bg-danger">Scrapped / Not Returnable</span>
                                        @else
                                            <span class="badge bg-success">Returned</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th width="40%">Condition:</th>
                                    <td>
                                        <span class="badge bg-{{ $assignment->condition_at_return == 'damaged' ? 'danger' : 'secondary' }}">
                                            {{ ucfirst(str_replace('_', ' ', $assignment->condition_at_return)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Meter Reading:</th>
                                    <td>
                                        @if($assignment->meter_reading_at_return)
                                            {{ number_format($assignment->meter_reading_at_return, 2) }} hrs
                                        @else
                                            <span class="text-muted">Not recorded</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Hours Used:</th>
                                    <td><strong>{{ number_format($assignment->operating_hours_used, 2) }} hrs</strong></td>
                                </tr>
                                @if(($assignment->return_disposition ?? null) === 'scrapped')
                                    <tr>
                                        <th>Damage Borne By:</th>
                                        <td>{{ ucfirst($assignment->damage_borne_by ?? 'company') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Recovery Amount:</th>
                                        <td>{{ number_format((float)($assignment->damage_recovery_amount ?? 0), 2) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Company Loss:</th>
                                        <td>{{ number_format((float)($assignment->damage_loss_amount ?? 0), 2) }}</td>
                                    </tr>
                                @endif
                                @if($assignment->return_remarks)
                                <tr>
                                    <th>Remarks:</th>
                                    <td>{{ $assignment->return_remarks }}</td>
                                </tr>
                                @endif
                            </table>
                            @else
                            <p class="text-muted">Machine not returned yet</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Status Card -->
            <div class="card mb-3 bg-{{ $assignment->isActive() ? 'warning' : 'success' }}">
                <div class="card-body text-white">
                    <h5>Current Status</h5>
                    <h2>{{ ucfirst($assignment->status) }}</h2>
                    @if($assignment->isOverdue())
                        <p class="mb-0"><i class="bi bi-exclamation-triangle"></i> Overdue Return</p>
                    @endif
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="card mb-3">
                <div class="card-header"><strong>Audit Trail</strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Issued By:</th>
                            <td>
                                @if($assignment->issuedBy)
                                    {{ $assignment->issuedBy->name }}
                                @else
                                    <span class="text-muted">Unknown</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Issued At:</th>
                            <td>{{ $assignment->created_at->format('d-M-Y H:i') }}</td>
                        </tr>
                        @if($assignment->returnedBy)
                        <tr>
                            <th>Returned By:</th>
                            <td>{{ $assignment->returnedBy->name }}</td>
                        </tr>
                        <tr>
                            <th>Returned At:</th>
                            <td>{{ $assignment->updated_at->format('d-M-Y H:i') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Accounting Vouchers (Tool Stock custody transfers) -->
            @if($assignment->issueVoucher || $assignment->returnVoucher)
                <div class="card mb-3">
                    <div class="card-header"><strong>Accounting Vouchers</strong></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th style="width: 45%">Issue Transfer</th>
                                <td>
                                    @if($assignment->issueVoucher)
                                        <a href="{{ route('accounting.vouchers.show', $assignment->issueVoucher) }}">{{ $assignment->issueVoucher->voucher_no }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Return / Settlement</th>
                                <td>
                                    @if($assignment->returnVoucher)
                                        <a href="{{ route('accounting.vouchers.show', $assignment->returnVoucher) }}">{{ $assignment->returnVoucher->voucher_no }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <div class="form-text mt-2">
                            *Auto-created only when the machine is marked as <strong>Tool Stock</strong>.
                        </div>
                    </div>
                </div>
            @endif

            @if($assignment->isActive())
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><strong>Quick Actions</strong></div>
                <div class="card-body">
                    @can('machinery.assignment.return')
                    <a href="{{ route('machine-assignments.return-form', $assignment) }}" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-arrow-return-left"></i> Return Machine
                    </a>
                    @endcan
                    @can('machinery.assignment.extend')
                    <a href="{{ route('machine-assignments.extend-form', $assignment) }}" class="btn btn-warning w-100">
                        <i class="bi bi-calendar-plus"></i> Extend Assignment
                    </a>
                    @endcan
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
