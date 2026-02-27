@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-plus-circle"></i> Issue Machine</h2>
        <a href="{{ route('machine-assignments.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    <form action="{{ route('machine-assignments.store') }}" method="POST">
        @csrf

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <!-- Machine Selection -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Machine Details</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Select Machine <span class="text-danger">*</span></label>
                            <select name="machine_id" class="form-select @error('machine_id') is-invalid @enderror" required id="machine_select">
                                <option value="">Choose a machine...</option>
                                @foreach($availableMachines as $machine)
                                <option value="{{ $machine->id }}" {{ old('machine_id') == $machine->id ? 'selected' : '' }}
                                    data-current-hours="{{ $machine->operating_hours_total }}"
                                    data-location="{{ $machine->current_location }}">
                                    {{ $machine->code }} - {{ $machine->name }}
                                    @if($machine->current_location)
                                        ({{ $machine->current_location }})
                                    @endif
                                </option>
                                @endforeach
                            </select>
                            @error('machine_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Only available machines are listed</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assigned Date <span class="text-danger">*</span></label>
                            <input type="date" name="assigned_date" class="form-control @error('assigned_date') is-invalid @enderror" 
                                   value="{{ old('assigned_date', date('Y-m-d')) }}" required>
                            @error('assigned_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Expected Return Date</label>
                            <input type="date" name="expected_return_date" class="form-control @error('expected_return_date') is-invalid @enderror" 
                                   value="{{ old('expected_return_date') }}">
                            @error('expected_return_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Optional - leave blank for indefinite assignment</small>
                        </div>
                    </div>
                </div>

                <!-- Assignment Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Assignment Information</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Assignment Type <span class="text-danger">*</span></label>
                            <select name="assignment_type" class="form-select @error('assignment_type') is-invalid @enderror" 
                                    required id="assignment_type">
                                <option value="">Select type...</option>
                                <option value="contractor" {{ old('assignment_type') == 'contractor' ? 'selected' : '' }}>
                                    Issue to Contractor
                                </option>
                                <option value="company_worker" {{ old('assignment_type') == 'company_worker' ? 'selected' : '' }}>
                                    Issue to Company Worker
                                </option>
                            </select>
                            @error('assignment_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Contractor Fields (show when contractor selected) -->
                        <div id="contractor_fields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Contractor <span class="text-danger">*</span></label>
                                <select name="contractor_party_id" class="form-select @error('contractor_party_id') is-invalid @enderror" 
                                        id="contractor_select">
                                    <option value="">Select contractor...</option>
                                    @foreach($contractors as $contractor)
                                    <option value="{{ $contractor->id }}" {{ old('contractor_party_id') == $contractor->id ? 'selected' : '' }}>
                                        {{ $contractor->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('contractor_party_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Person Name <span class="text-danger">*</span></label>
                                <input type="text" name="contractor_person_name" 
                                       class="form-control @error('contractor_person_name') is-invalid @enderror" 
                                       value="{{ old('contractor_person_name') }}"
                                       placeholder="Enter person's name">
                                @error('contractor_person_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Worker Fields (show when company_worker selected) -->
                        <div id="worker_fields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Company Worker <span class="text-danger">*</span></label>
                                <select name="worker_user_id" class="form-select @error('worker_user_id') is-invalid @enderror" 
                                        id="worker_select">
                                    <option value="">Select worker...</option>
                                    @foreach($workers as $worker)
                                    <option value="{{ $worker->id }}" {{ old('worker_user_id') == $worker->id ? 'selected' : '' }}>
                                        {{ $worker->name }} @if($worker->employee_code) ({{ $worker->employee_code }}) @endif
                                    </option>
                                    @endforeach
                                </select>
                                @error('worker_user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Project (Optional)</label>
                            <select name="project_id" class="form-select @error('project_id') is-invalid @enderror">
                                <option value="">No project</option>
                                @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('project_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <!-- Condition & Meter Reading -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Machine Condition at Issue</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Condition <span class="text-danger">*</span></label>
                            <select name="condition_at_issue" class="form-select @error('condition_at_issue') is-invalid @enderror" required>
                                <option value="excellent" {{ old('condition_at_issue') == 'excellent' ? 'selected' : '' }}>
                                    Excellent
                                </option>
                                <option value="good" {{ old('condition_at_issue', 'good') == 'good' ? 'selected' : '' }}>
                                    Good
                                </option>
                                <option value="fair" {{ old('condition_at_issue') == 'fair' ? 'selected' : '' }}>
                                    Fair
                                </option>
                                <option value="requires_attention" {{ old('condition_at_issue') == 'requires_attention' ? 'selected' : '' }}>
                                    Requires Attention
                                </option>
                            </select>
                            @error('condition_at_issue')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Meter Reading at Issue</label>
                            <input type="number" name="meter_reading_at_issue" step="0.01"
                                   class="form-control @error('meter_reading_at_issue') is-invalid @enderror" 
                                   value="{{ old('meter_reading_at_issue') }}"
                                   placeholder="Enter current meter reading (hours)">
                            @error('meter_reading_at_issue')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">For machines with hour meters/odometers</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Issue Remarks</label>
                            <textarea name="issue_remarks" class="form-control @error('issue_remarks') is-invalid @enderror" 
                                      rows="3">{{ old('issue_remarks') }}</textarea>
                            @error('issue_remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Any special instructions or notes</small>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="bi bi-info-circle"></i> Important Notes</h6>
                        <ul class="small mb-0">
                            <li>Machine will be marked as "Issued" and unavailable for other assignments</li>
                            <li>Return date is optional but recommended for tracking</li>
                            <li>Record meter reading for accurate operating hours tracking</li>
                            <li>Document condition to compare on return</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Issue Machine
                </button>
                <a href="{{ route('machine-assignments.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const assignmentType = document.getElementById('assignment_type');
    const contractorFields = document.getElementById('contractor_fields');
    const workerFields = document.getElementById('worker_fields');
    const contractorSelect = document.getElementById('contractor_select');
    const workerSelect = document.getElementById('worker_select');

    // Toggle fields based on assignment type
    assignmentType.addEventListener('change', function() {
        if (this.value === 'contractor') {
            contractorFields.style.display = 'block';
            workerFields.style.display = 'none';
            contractorSelect.required = true;
            workerSelect.required = false;
            document.querySelector('[name="contractor_person_name"]').required = true;
        } else if (this.value === 'company_worker') {
            contractorFields.style.display = 'none';
            workerFields.style.display = 'block';
            contractorSelect.required = false;
            workerSelect.required = true;
            document.querySelector('[name="contractor_person_name"]').required = false;
        } else {
            contractorFields.style.display = 'none';
            workerFields.style.display = 'none';
            contractorSelect.required = false;
            workerSelect.required = false;
        }
    });

    // Trigger on page load if value already selected
    if (assignmentType.value) {
        assignmentType.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
@endsection
