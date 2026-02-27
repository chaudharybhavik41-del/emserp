@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-clipboard-check"></i> Calibration: {{ $calibration->calibration_number }}</h2>
        <div>
            @can('machinery.calibration.update')
            <a href="{{ route('machine-calibrations.edit', $calibration) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            @endcan
            <a href="{{ route('machine-calibrations.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-md-8">
            <!-- Calibration Details -->
            <div class="card mb-3">
                <div class="card-header"><strong>Calibration Details</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Calibration #:</th>
                                    <td><strong>{{ $calibration->calibration_number }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Machine:</th>
                                    <td>
                                        <a href="{{ route('machines.show', $calibration->machine) }}">
                                            {{ $calibration->machine->code }}
                                        </a>
                                        <br>{{ $calibration->machine->name }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Calibration Date:</th>
                                    <td>{{ $calibration->calibration_date->format('d-M-Y') }}</td>
                                </tr>
                                <tr>
                                    <th>Due Date:</th>
                                    <td>{{ $calibration->due_date ? $calibration->due_date->format('d-M-Y') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Next Due Date:</th>
                                    <td>
                                        {{ $calibration->next_due_date->format('d-M-Y') }}
                                        @if($calibration->isOverdue())
                                            <br><span class="badge bg-danger">OVERDUE</span>
                                        @elseif($calibration->isDueSoon())
                                            <br><span class="badge bg-warning text-dark">DUE SOON</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Agency:</th>
                                    <td>{{ $calibration->calibration_agency }}</td>
                                </tr>
                                <tr>
                                    <th>Certificate #:</th>
                                    <td>{{ $calibration->certificate_number ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Standard:</th>
                                    <td>{{ $calibration->standard_followed ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Result:</th>
                                    <td>
                                        <span class="badge bg-{{ $calibration->getResultBadgeClass() }} fs-6">
                                            {{ ucfirst(str_replace('_', ' ', $calibration->result)) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-{{ $calibration->getStatusBadgeClass() }} fs-6">
                                            {{ ucfirst($calibration->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parameters & Observations -->
            <div class="card mb-3">
                <div class="card-header"><strong>Calibration Details</strong></div>
                <div class="card-body">
                    @if($calibration->parameters_calibrated)
                    <div class="mb-3">
                        <h6>Parameters Calibrated:</h6>
                        <div class="border p-3 bg-light rounded">
                            {{ $calibration->parameters_calibrated }}
                        </div>
                    </div>
                    @endif

                    @if($calibration->observations)
                    <div class="mb-3">
                        <h6>Observations:</h6>
                        <div class="border p-3 bg-light rounded">
                            {{ $calibration->observations }}
                        </div>
                    </div>
                    @endif

                    @if($calibration->remarks)
                    <div>
                        <h6>Remarks:</h6>
                        <div class="border p-3 bg-light rounded">
                            {{ $calibration->remarks }}
                        </div>
                    </div>
                    @endif

                    @if(!$calibration->parameters_calibrated && !$calibration->observations && !$calibration->remarks)
                    <p class="text-muted mb-0">No additional details recorded.</p>
                    @endif
                </div>
            </div>

            <!-- Documents -->
            <div class="card mb-3">
                <div class="card-header"><strong>Documents</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Calibration Certificate</h6>
                            @if($calibration->hasCertificate())
                                <a href="{{ $calibration->getCertificateUrl() }}" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-file-pdf"></i> View Certificate
                                </a>
                                <a href="{{ $calibration->getCertificateUrl() }}" download class="btn btn-outline-secondary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            @else
                                <p class="text-muted">No certificate uploaded</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6>Calibration Report</h6>
                            @if($calibration->hasReport())
                                <a href="{{ $calibration->getReportUrl() }}" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-file-pdf"></i> View Report
                                </a>
                                <a href="{{ $calibration->getReportUrl() }}" download class="btn btn-outline-secondary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            @else
                                <p class="text-muted">No report uploaded</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Status Card -->
            <div class="card mb-3 bg-{{ $calibration->getStatusBadgeClass() }}">
                <div class="card-body text-white">
                    <h5>Calibration Status</h5>
                    <h2>{{ ucfirst($calibration->status) }}</h2>
                    @if($calibration->isOverdue())
                        <p class="mb-0"><i class="bi bi-exclamation-triangle"></i> Overdue for Next Calibration</p>
                    @elseif($calibration->isDueSoon())
                        <p class="mb-0"><i class="bi bi-calendar-event"></i> Due Soon</p>
                    @endif
                </div>
            </div>

            <!-- Cost -->
            @if($calibration->calibration_cost > 0)
            <div class="card mb-3">
                <div class="card-header"><strong>Cost</strong></div>
                <div class="card-body">
                    <h3 class="mb-0">₹{{ number_format($calibration->calibration_cost, 2) }}</h3>
                    <small class="text-muted">Calibration Cost</small>
                </div>
            </div>
            @endif

            <!-- Personnel -->
            <div class="card mb-3">
                <div class="card-header"><strong>Personnel</strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        @if($calibration->performer)
                        <tr>
                            <th>Performed By:</th>
                            <td>{{ $calibration->performer->name }}</td>
                        </tr>
                        @endif
                        @if($calibration->verifier)
                        <tr>
                            <th>Verified By:</th>
                            <td>{{ $calibration->verifier->name }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="card">
                <div class="card-header"><strong>Audit Trail</strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Created By:</th>
                            <td>
                                @if($calibration->creator)
                                    {{ $calibration->creator->name }}
                                @else
                                    <span class="text-muted">Unknown</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Created At:</th>
                            <td>{{ $calibration->created_at->format('d-M-Y H:i') }}</td>
                        </tr>
                        @if($calibration->updated_at != $calibration->created_at)
                        <tr>
                            <th>Last Updated:</th>
                            <td>{{ $calibration->updated_at->format('d-M-Y H:i') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
