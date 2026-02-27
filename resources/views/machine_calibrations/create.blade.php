@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-plus-circle"></i> Record Calibration</h2>
        <a href="{{ route('machine-calibrations.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    <form action="{{ route('machine-calibrations.store') }}" method="POST" enctype="multipart/form-data">
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
                            <select name="machine_id" class="form-select @error('machine_id') is-invalid @enderror" 
                                    required id="machine_select">
                                <option value="">Choose a machine...</option>
                                @foreach($machines as $machine)
                                <option value="{{ $machine->id }}" 
                                        {{ old('machine_id', request('machine_id')) == $machine->id ? 'selected' : '' }}
                                        data-frequency="{{ $machine->calibration_frequency_months }}"
                                        data-agency="{{ $machine->calibration_agency }}">
                                    {{ $machine->code }} - {{ $machine->name }}
                                    @if($machine->last_calibration_date)
                                        (Last: {{ $machine->last_calibration_date->format('d-M-Y') }})
                                    @endif
                                </option>
                                @endforeach
                            </select>
                            @error('machine_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Only machines requiring calibration are listed</small>
                        </div>
                    </div>
                </div>

                <!-- Calibration Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Calibration Information</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Calibration Date <span class="text-danger">*</span></label>
                                <input type="date" name="calibration_date" 
                                       class="form-control @error('calibration_date') is-invalid @enderror" 
                                       value="{{ old('calibration_date', date('Y-m-d')) }}" 
                                       max="{{ date('Y-m-d') }}"
                                       required>
                                @error('calibration_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" 
                                       class="form-control @error('due_date') is-invalid @enderror" 
                                       value="{{ old('due_date') }}" 
                                       readonly>
                                @error('due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Auto-calculated based on frequency</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Calibration Agency <span class="text-danger">*</span></label>
                            <input type="text" name="calibration_agency" 
                                   class="form-control @error('calibration_agency') is-invalid @enderror" 
                                   value="{{ old('calibration_agency') }}"
                                   placeholder="Enter calibration agency name"
                                   required>
                            @error('calibration_agency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Certificate Number</label>
                                <input type="text" name="certificate_number" 
                                       class="form-control @error('certificate_number') is-invalid @enderror" 
                                       value="{{ old('certificate_number') }}"
                                       placeholder="e.g., CAL/2024/12345">
                                @error('certificate_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Standard Followed</label>
                                <input type="text" name="standard_followed" 
                                       class="form-control @error('standard_followed') is-invalid @enderror" 
                                       value="{{ old('standard_followed') }}"
                                       placeholder="e.g., ISO/IEC 17025">
                                @error('standard_followed')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parameters Calibrated</label>
                            <textarea name="parameters_calibrated" class="form-control @error('parameters_calibrated') is-invalid @enderror" 
                                      rows="3" placeholder="e.g., Temperature, Pressure, Voltage...">{{ old('parameters_calibrated') }}</textarea>
                            @error('parameters_calibrated')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">List all parameters that were calibrated</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Calibration Result <span class="text-danger">*</span></label>
                            <select name="result" class="form-select @error('result') is-invalid @enderror" required>
                                <option value="">Select result...</option>
                                <option value="pass" {{ old('result') == 'pass' ? 'selected' : '' }}>
                                    Pass - Within tolerance
                                </option>
                                <option value="pass_with_adjustment" {{ old('result') == 'pass_with_adjustment' ? 'selected' : '' }}>
                                    Pass with Adjustment - Adjusted to tolerance
                                </option>
                                <option value="fail" {{ old('result') == 'fail' ? 'selected' : '' }}>
                                    Fail - Out of tolerance
                                </option>
                            </select>
                            @error('result')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observations</label>
                            <textarea name="observations" class="form-control @error('observations') is-invalid @enderror" 
                                      rows="3" placeholder="Any observations during calibration...">{{ old('observations') }}</textarea>
                            @error('observations')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" 
                                      rows="2" placeholder="Additional remarks or notes...">{{ old('remarks') }}</textarea>
                            @error('remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <!-- File Uploads -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Documents</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Calibration Certificate</label>
                            <input type="file" name="certificate_file" 
                                   class="form-control @error('certificate_file') is-invalid @enderror" 
                                   accept=".pdf,.jpg,.jpeg,.png">
                            @error('certificate_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Accepted: PDF, JPG, PNG (Max: 10MB)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Calibration Report</label>
                            <input type="file" name="report_file" 
                                   class="form-control @error('report_file') is-invalid @enderror" 
                                   accept=".pdf,.jpg,.jpeg,.png">
                            @error('report_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Accepted: PDF, JPG, PNG (Max: 10MB)</small>
                        </div>
                    </div>
                </div>

                <!-- Personnel & Cost -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Personnel & Cost</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Performed By</label>
                            <select name="performed_by" class="form-select @error('performed_by') is-invalid @enderror">
                                <option value="">Select user...</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('performed_by', auth()->id()) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('performed_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Verified By</label>
                            <select name="verified_by" class="form-select @error('verified_by') is-invalid @enderror">
                                <option value="">Select user...</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('verified_by') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('verified_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Calibration Cost</label>
                            <input type="number" name="calibration_cost" step="0.01"
                                   class="form-control @error('calibration_cost') is-invalid @enderror" 
                                   value="{{ old('calibration_cost', 0) }}"
                                   placeholder="0.00">
                            @error('calibration_cost')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Total cost for this calibration</small>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="bi bi-info-circle"></i> Important Notes</h6>
                        <ul class="small mb-0">
                            <li>Next due date is auto-calculated based on machine's calibration frequency</li>
                            <li>Upload calibration certificate for compliance records</li>
                            <li>Certificate and report files should be clear and readable</li>
                            <li>If calibration fails, consider taking machine out of service</li>
                            <li>System will alert 15 days before next due date</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Record Calibration
                </button>
                <a href="{{ route('machine-calibrations.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const machineSelect = document.getElementById('machine_select');
    const calibrationDate = document.querySelector('[name="calibration_date"]');
    const dueDate = document.querySelector('[name="due_date"]');
    const agencyInput = document.querySelector('[name="calibration_agency"]');

    // Auto-calculate due date based on machine frequency
    function calculateDueDate() {
        const selectedOption = machineSelect.options[machineSelect.selectedIndex];
        const frequency = parseInt(selectedOption.dataset.frequency || 12);
        const agency = selectedOption.dataset.agency || '';
        const calDate = new Date(calibrationDate.value);
        
        if (calibrationDate.value && frequency) {
            calDate.setMonth(calDate.getMonth() + frequency);
            dueDate.value = calDate.toISOString().split('T')[0];
        }
        
        if (agency && !agencyInput.value) {
            agencyInput.value = agency;
        }
    }

    machineSelect.addEventListener('change', calculateDueDate);
    calibrationDate.addEventListener('change', calculateDueDate);

    // Trigger on load if machine pre-selected
    if (machineSelect.value && calibrationDate.value) {
        calculateDueDate();
    }
});
</script>
@endpush
@endsection
