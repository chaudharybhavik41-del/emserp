@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-plus-circle"></i> Add New Machine</h2>
        <a href="{{ route('machines.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    <form action="{{ route('machines.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <!-- Identity Section -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Identity</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Machine Category <span class="text-danger">*</span></label>
                            <select name="material_category_id" class="form-select @error('material_category_id') is-invalid @enderror" required>
                                <option value="">Select Category</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('material_category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('material_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Machine Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Short Name</label>
                            <input type="text" name="short_name" class="form-control @error('short_name') is-invalid @enderror" 
                                   value="{{ old('short_name') }}">
                            @error('short_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror" 
                                   value="{{ old('serial_number') }}" required>
                            @error('serial_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Specifications -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Specifications</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Make</label>
                                <input type="text" name="make" class="form-control" value="{{ old('make') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control" value="{{ old('model') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grade/Capacity</label>
                                <input type="text" name="grade" class="form-control" value="{{ old('grade') }}" 
                                       placeholder="e.g., 1.5 inch mild steel">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year of Manufacture</label>
                                <input type="number" name="year_of_manufacture" class="form-control" 
                                       value="{{ old('year_of_manufacture') }}" min="1900" max="{{ date('Y') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rated Capacity</label>
                                <input type="text" name="rated_capacity" class="form-control" value="{{ old('rated_capacity') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Power Rating</label>
                                <input type="text" name="power_rating" class="form-control" value="{{ old('power_rating') }}" 
                                       placeholder="e.g., 5 HP, 380V">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fuel Type</label>
                            <select name="fuel_type" class="form-select">
                                <option value="">Select</option>
                                <option value="electric" {{ old('fuel_type') == 'electric' ? 'selected' : '' }}>Electric</option>
                                <option value="diesel" {{ old('fuel_type') == 'diesel' ? 'selected' : '' }}>Diesel</option>
                                <option value="gas" {{ old('fuel_type') == 'gas' ? 'selected' : '' }}>Gas</option>
                                <option value="hydraulic" {{ old('fuel_type') == 'hydraulic' ? 'selected' : '' }}>Hydraulic</option>
                                <option value="manual" {{ old('fuel_type') == 'manual' ? 'selected' : '' }}>Manual</option>
                                <option value="other" {{ old('fuel_type') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Specifications</label>
                            <textarea name="spec" class="form-control" rows="3">{{ old('spec') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <!-- Purchase Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Purchase Details</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_party_id" class="form-select">
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_party_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price</label>
                                <input type="number" name="purchase_price" class="form-control" 
                                       value="{{ old('purchase_price', 0) }}" min="0" step="0.01">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Accounting Treatment</label>
                            <select name="accounting_treatment" class="form-select @error('accounting_treatment') is-invalid @enderror">
                                <option value="">Use Material Type Default</option>
                                <option value="fixed_asset" {{ old('accounting_treatment') == 'fixed_asset' ? 'selected' : '' }}>Long-term Fixed Asset (Depreciable)</option>
                                <option value="tool_stock" {{ old('accounting_treatment') == 'tool_stock' ? 'selected' : '' }}>Short-term Tool Stock (Inventory)</option>
                            </select>
                            @error('accounting_treatment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Tool Stock moves between <code>INV-TOOLS</code> and custody ledger when issued/returned.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Invoice Number</label>
                                <input type="text" name="purchase_invoice_no" class="form-control" 
                                       value="{{ old('purchase_invoice_no') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty (Months)</label>
                                <input type="number" name="warranty_months" class="form-control" 
                                       value="{{ old('warranty_months', 0) }}" min="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operational Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Operational Details</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Location</label>
                            <input type="text" name="current_location" class="form-control" 
                                   value="{{ old('current_location') }}" placeholder="e.g., Workshop A, Shed 2">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="under_maintenance" {{ old('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                                <option value="breakdown" {{ old('status') == 'breakdown' ? 'selected' : '' }}>Breakdown</option>
                                <option value="retired" {{ old('status') == 'retired' ? 'selected' : '' }}>Retired</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Maintenance Frequency (Days)</label>
                            <input type="number" name="maintenance_frequency_days" class="form-control" 
                                   value="{{ old('maintenance_frequency_days', 90) }}" min="1">
                            <small class="text-muted">Default: 90 days (quarterly)</small>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" 
                                   id="is_active" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                <strong>Machine is Active</strong>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<!-- Calibration Settings -->
	<div class="card mb-3">
    <div class="card-header"><strong>Calibration Settings</strong></div>
    <div class="card-body">
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" name="requires_calibration" value="1" 
                       class="form-check-input" id="requires_calibration"
                       {{ old('requires_calibration', $machine->requires_calibration ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="requires_calibration">
                    <strong>Requires Calibration</strong>
                </label>
            </div>
            <small class="text-muted">Check if this machine needs periodic calibration (for precision instruments)</small>
        </div>

        <div id="calibration_fields" style="display: none;">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> <strong>Calibration Tracking:</strong> 
                System will track calibration due dates and send alerts.
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Calibration Frequency <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="calibration_frequency_months" 
                               class="form-control @error('calibration_frequency_months') is-invalid @enderror" 
                               value="{{ old('calibration_frequency_months', $machine->calibration_frequency_months ?? 12) }}"
                               min="1" max="60" placeholder="12">
                        <span class="input-group-text">months</span>
                    </div>
                    @error('calibration_frequency_months')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Examples: 6 (semi-annual), 12 (annual), 24 (biennial)</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Default Calibration Agency</label>
                    <input type="text" name="calibration_agency" 
                           class="form-control @error('calibration_agency') is-invalid @enderror" 
                           value="{{ old('calibration_agency', $machine->calibration_agency ?? '') }}"
                           placeholder="e.g., ABC Calibration Services">
                    @error('calibration_agency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Preferred agency for calibrations (can be changed per calibration)</small>
                </div>
            </div>

            @if(isset($machine) && $machine->last_calibration_date)
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Last Calibration Date</label>
                    <input type="text" class="form-control" 
                           value="{{ $machine->last_calibration_date->format('d-M-Y') }}" 
                           readonly disabled>
                    <small class="text-muted">System managed - updated when calibration recorded</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Next Due Date</label>
                    <input type="text" class="form-control" 
                           value="{{ $machine->next_calibration_due_date?->format('d-M-Y') ?? 'Not set' }}" 
                           readonly disabled>
                    <small class="text-muted">Auto-calculated based on frequency</small>
                </div>
            </div>
            @endif
        </div>
    </div>
	</div>

	@push('scripts')
	<script>
	document.addEventListener('DOMContentLoaded', function() {
    const requiresCalibration = document.getElementById('requires_calibration');
    const calibrationFields = document.getElementById('calibration_fields');
    
    function toggleCalibrationFields() {
        if (requiresCalibration.checked) {
            calibrationFields.style.display = 'block';
            // Make frequency required when checked
            document.querySelector('[name="calibration_frequency_months"]').required = true;
        } else {
            calibrationFields.style.display = 'none';
            document.querySelector('[name="calibration_frequency_months"]').required = false;
        }
    }
    
    requiresCalibration.addEventListener('change', toggleCalibrationFields);
    toggleCalibrationFields(); // Run on page load
	});
		</script>
		@endpush
      <!-- Submit Buttons -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Create Machine
                </button>
                <a href="{{ route('machines.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
