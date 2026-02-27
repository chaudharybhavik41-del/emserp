@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-calendar-plus"></i> Extend Assignment</h2>
        <a href="{{ route('machine-assignments.show', $assignment) }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <!-- Assignment Summary -->
    <div class="card mb-3 bg-light">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Assignment #:</strong><br>
                    {{ $assignment->assignment_number }}
                </div>
                <div class="col-md-3">
                    <strong>Machine:</strong><br>
                    {{ $assignment->machine->code }} - {{ $assignment->machine->name }}
                </div>
                <div class="col-md-3">
                    <strong>Assigned To:</strong><br>
                    {{ $assignment->getAssignedToName() }}
                </div>
                <div class="col-md-3">
                    <strong>Current Expected Return:</strong><br>
                    @if($assignment->expected_return_date)
                        {{ $assignment->expected_return_date->format('d-M-Y') }}
                        @if($assignment->isOverdue())
                            <br><span class="badge bg-danger">OVERDUE</span>
                        @endif
                    @else
                        <span class="text-muted">Not specified</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('machine-assignments.process-extend', $assignment) }}" method="POST">
        @csrf

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <!-- Extension Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Extension Details</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">New Expected Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="new_expected_return_date" 
                                   class="form-control @error('new_expected_return_date') is-invalid @enderror" 
                                   value="{{ old('new_expected_return_date') }}"
                                   min="{{ date('Y-m-d') }}"
                                   required>
                            @error('new_expected_return_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">
                                @if($assignment->expected_return_date)
                                    Current expected return: {{ $assignment->expected_return_date->format('d-M-Y') }}
                                @endif
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason for Extension <span class="text-danger">*</span></label>
                            <textarea name="extension_reason" class="form-control @error('extension_reason') is-invalid @enderror" 
                                      rows="5" required placeholder="Explain why extension is needed...">{{ old('extension_reason') }}</textarea>
                            @error('extension_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Be specific about project requirements, delays, or other reasons</small>
                        </div>
                    </div>
                </div>

                <!-- Important Notes -->
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6><i class="bi bi-info-circle"></i> Extension Policy</h6>
                        <ul class="small mb-0">
                            <li>Extensions must be requested before the expected return date when possible</li>
                            <li>Provide clear justification for the extension</li>
                            <li>Assignment status will be updated to "Extended"</li>
                            <li>Multiple extensions may be granted based on business needs</li>
                            <li>Machine remains unavailable for other assignments during extension</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <!-- Assignment Timeline -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Assignment Timeline</strong></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th width="40%">Assigned Date:</th>
                                <td>{{ $assignment->assigned_date->format('d-M-Y') }}</td>
                            </tr>
                            <tr>
                                <th>Original Expected Return:</th>
                                <td>
                                    @if($assignment->expected_return_date)
                                        {{ $assignment->expected_return_date->format('d-M-Y') }}
                                    @else
                                        <span class="text-muted">Not specified</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Days Used So Far:</th>
                                <td><strong>{{ $assignment->getDurationDays() }} days</strong></td>
                            </tr>
                            @if($assignment->isOverdue())
                            <tr>
                                <th>Status:</th>
                                <td><span class="badge bg-danger">OVERDUE</span></td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <!-- Machine Info -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Machine Information</strong></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th width="40%">Machine Code:</th>
                                <td>{{ $assignment->machine->code }}</td>
                            </tr>
                            <tr>
                                <th>Name:</th>
                                <td>{{ $assignment->machine->name }}</td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-{{ $assignment->machine->getStatusBadgeClass() }}">
                                        {{ ucfirst(str_replace('_', ' ', $assignment->machine->status)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Category:</th>
                                <td>{{ $assignment->machine->category->name ?? 'N/A' }}</td>
                            </tr>
                            @if($assignment->project)
                            <tr>
                                <th>Project:</th>
                                <td>{{ $assignment->project->name }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <!-- Assignment Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Assignment Details</strong></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th width="40%">Assignment Type:</th>
                                <td>
                                    <span class="badge bg-{{ $assignment->assignment_type == 'contractor' ? 'info' : 'primary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $assignment->assignment_type)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Assigned To:</th>
                                <td>{{ $assignment->getAssignedToName() }}</td>
                            </tr>
                            <tr>
                                <th>Condition at Issue:</th>
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ ucfirst(str_replace('_', ' ', $assignment->condition_at_issue)) }}
                                    </span>
                                </td>
                            </tr>
                            @if($assignment->meter_reading_at_issue)
                            <tr>
                                <th>Meter Reading:</th>
                                <td>{{ number_format($assignment->meter_reading_at_issue, 2) }} hrs</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-warning btn-lg">
                    <i class="bi bi-check-circle"></i> Extend Assignment
                </button>
                <a href="{{ route('machine-assignments.show', $assignment) }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
