@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-pencil"></i> Edit Calibration: {{ $calibration->calibration_number }}</h2>
        <a href="{{ route('machine-calibrations.show', $calibration) }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <form action="{{ route('machine-calibrations.update', $calibration) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <!-- Machine (Read-only) -->
                <div class="card mb-3 bg-light">
                    <div class="card-body">
                        <strong>Machine:</strong> {{ $calibration->machine->code }} - {{ $calibration->machine->name }}
                        <input type="hidden" name="machine_id" value="{{ $calibration->machine_id }}">
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
                                       value="{{ old('calibration_date', $calibration->calibration_date->format('Y-m-d')) }}" 
                                       required>
                                @error('calibration_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Next Due Date</label>
                                <input type="date" name="next_due_date" 
                                       class="form-control @error('next_due_date') is-invalid @enderror" 
                                       value="{{ old('next_due_date', $calibration->next_due_date->format('Y-m-d')) }}">
                                @error('next_due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Calibration Agency <span class="text-danger">*</span></label>
                            <input type="text" name="calibration_agency" 
                                   class="form-control @error('calibration_agency') is-invalid @enderror" 
                                   value="{{ old('calibration_agency', $calibration->calibration_agency) }}"
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
                                       value="{{ old('certificate_number', $calibration->certificate_number) }}">
                                @error('certificate_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Standard Followed</label>
                                <input type="text" name="standard_followed" 
                                       class="form-control @error('standard_followed') is-invalid @enderror" 
                                       value="{{ old('standard_followed', $calibration->standard_followed) }}">
                                @error('standard_followed')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parameters Calibrated</label>
                            <textarea name="parameters_calibrated" class="form-control @error('parameters_calibrated') is-invalid @enderror" 
                                      rows="3">{{ old('parameters_calibrated', $calibration->parameters_calibrated) }}</textarea>
                            @error('parameters_calibrated')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Calibration Result <span class="text-danger">*</span></label>
                            <select name="result" class="form-select @error('result') is-invalid @enderror" required>
                                <option value="pass" {{ old('result', $calibration->result) == 'pass' ? 'selected' : '' }}>
                                    Pass - Within tolerance
                                </option>
                                <option value="pass_with_adjustment" {{ old('result', $calibration->result) == 'pass_with_adjustment' ? 'selected' : '' }}>
                                    Pass with Adjustment
                                </option>
                                <option value="fail" {{ old('result', $calibration->result) == 'fail' ? 'selected' : '' }}>
                                    Fail - Out of tolerance
                                </option>
                            </select>
                            @error('result')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="completed" {{ old('status', $calibration->status) == 'completed' ? 'selected' : '' }}>
                                    Completed
                                </option>
                                <option value="scheduled" {{ old('status', $calibration->status) == 'scheduled' ? 'selected' : '' }}>
                                    Scheduled
                                </option>
                                <option value="overdue" {{ old('status', $calibration->status) == 'overdue' ? 'selected' : '' }}>
                                    Overdue
                                </option>
                                <option value="cancelled" {{ old('status', $calibration->status) == 'cancelled' ? 'selected' : '' }}>
                                    Cancelled
                                </option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observations</label>
                            <textarea name="observations" class="form-control @error('observations') is-invalid @enderror" 
                                      rows="3">{{ old('observations', $calibration->observations) }}</textarea>
                            @error('observations')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" 
                                      rows="2">{{ old('remarks', $calibration->remarks) }}</textarea>
                            @error('remarks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <!-- Current Documents -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Current Documents</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Current Certificate:</h6>
                            @if($calibration->hasCertificate())
                                <a href="{{ $calibration->getCertificateUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-pdf"></i> View Current
                                </a>
                                <p class="small text-muted mt-2">Upload new file to replace</p>
                            @else
                                <p class="text-muted">No certificate uploaded</p>
                            @endif
                        </div>

                        <div>
                            <h6>Current Report:</h6>
                            @if($calibration->hasReport())
                                <a href="{{ $calibration->getReportUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-pdf"></i> View Current
                                </a>
                                <p class="small text-muted mt-2">Upload new file to replace</p>
                            @else
                                <p class="text-muted">No report uploaded</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Upload New Documents -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Upload New Documents</strong></div>
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
                                <option value="{{ $user->id }}" {{ old('performed_by', $calibration->performed_by) == $user->id ? 'selected' : '' }}>
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
                                <option value="{{ $user->id }}" {{ old('verified_by', $calibration->verified_by) == $user->id ? 'selected' : '' }}>
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
                                   value="{{ old('calibration_cost', $calibration->calibration_cost) }}">
                            @error('calibration_cost')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Update Calibration
                </button>
                <a href="{{ route('machine-calibrations.show', $calibration) }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
