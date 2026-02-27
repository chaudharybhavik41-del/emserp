--------------------------------------------------------------------------------

@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-pencil"></i> Edit Machine: {{ $machine->code }}</h2>
        <div>
            <a href="{{ route('machines.show', $machine) }}" class="btn btn-info">
                <i class="bi bi-eye"></i> View Details
            </a>
            <a href="{{ route('machines.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <form action="{{ route('machines.update', $machine) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Hidden fields that shouldn't change -->
        <input type="hidden" name="material_type_id" value="{{ $machine->material_type_id }}">
        <input type="hidden" name="code" value="{{ $machine->code }}">

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-6">
                <!-- Basic Information -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Basic Information</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Machine Code</label>
                            <input type="text" class="form-control" value="{{ $machine->code }}" disabled>
                            <small class="text-muted">Auto-generated, cannot be changed</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Machine Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $machine->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Short Name</label>
                            <input type="text" name="short_name" class="form-control @error('short_name') is-invalid @enderror" 
                                   value="{{ old('short_name', $machine->short_name) }}">
                            @error('short_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Material Category <span class="text-danger">*</span></label>
                                <select name="material_category_id" class="form-select @error('material_category_id') is-invalid @enderror" required>
                                    @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('material_category_id', $machine->material_category_id) == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('material_category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subcategory</label>
                                <select name="material_subcategory_id" class="form-select @error('material_subcategory_id') is-invalid @enderror">
                                    <option value="">None</option>
                                    @foreach($subcategories as $subcategory)
                                    <option value="{{ $subcategory->id }}" {{ old('material_subcategory_id', $machine->material_subcategory_id) == $subcategory->id ? 'selected' : '' }}>
                                        {{ $subcategory->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('material_subcategory_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror" 
                                   value="{{ old('serial_number', $machine->serial_number) }}" required>
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
                                <label class="form-label">Make/Manufacturer</label>
                                <input type="text" name="make" class="form-control @error('make') is-invalid @enderror" 
                                       value="{{ old('make', $machine->make) }}">
                                @error('make')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control @error('model') is-invalid @enderror" 
                                       value="{{ old('model', $machine->model) }}">
                                @error('model')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grade/Capacity</label>
                                <input type="text" name="grade" class="form-control @error('grade') is-invalid @enderror" 
                                       value="{{ old('grade', $machine->grade) }}">
                                @error('grade')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year of Manufacture</label>
                                <input type="number" name="year_of_manufacture" class="form-control @error('year_of_manufacture') is-invalid @enderror" 
                                       value="{{ old('year_of_manufacture', $machine->year_of_manufacture) }}" min="1900" max="{{ date('Y') + 1 }}">
                                @error('year_of_manufacture')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rated Capacity</label>
                                <input type="text" name="rated_capacity" class="form-control @error('rated_capacity') is-invalid @enderror" 
                                       value="{{ old('rated_capacity', $machine->rated_capacity) }}" placeholder="e.g., 5 tons">
                                @error('rated_capacity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Power Rating</label>
                                <input type="text" name="power_rating" class="form-control @error('power_rating') is-invalid @enderror" 
                                       value="{{ old('power_rating', $machine->power_rating) }}" placeholder="e.g., 15 HP">
                                @error('power_rating')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fuel Type</label>
                            <select name="fuel_type" class="form-select @error('fuel_type') is-invalid @enderror">
                                <option value="">Select type...</option>
                                <option value="electric" {{ old('fuel_type', $machine->fuel_type) == 'electric' ? 'selected' : '' }}>Electric</option>
                                <option value="diesel" {{ old('fuel_type', $machine->fuel_type) == 'diesel' ? 'selected' : '' }}>Diesel</option>
                                <option value="gas" {{ old('fuel_type', $machine->fuel_type) == 'gas' ? 'selected' : '' }}>Gas</option>
                                <option value="hydraulic" {{ old('fuel_type', $machine->fuel_type) == 'hydraulic' ? 'selected' : '' }}>Hydraulic</option>
                                <option value="manual" {{ old('fuel_type', $machine->fuel_type) == 'manual' ? 'selected' : '' }}>Manual</option>
                                <option value="other" {{ old('fuel_type', $machine->fuel_type) == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('fuel_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Specifications</label>
                            <textarea name="spec" class="form-control @error('spec') is-invalid @enderror" 
                                      rows="3">{{ old('spec', $machine->spec) }}</textarea>
                            @error('spec')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Purchase Details -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Purchase Details</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_party_id" class="form-select @error('supplier_party_id') is-invalid @enderror">
                                <option value="">Select supplier...</option>
                                @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_party_id', $machine->supplier_party_id) == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('supplier_party_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror"
                                       value="{{ old('purchase_date', $machine->purchase_date?->format('Y-m-d')) }}">
                                @error('purchase_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price</label>
                                <input type="number" name="purchase_price" step="0.01" class="form-control @error('purchase_price') is-invalid @enderror"
                                       value="{{ old('purchase_price', $machine->purchase_price) }}" min="0">
                                @error('purchase_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Accounting Treatment</label>
                            <select name="accounting_treatment" class="form-select @error('accounting_treatment') is-invalid @enderror">
                                <option value="">Use Material Type Default</option>
                                <option value="fixed_asset" {{ old('accounting_treatment', $machine->accounting_treatment) == 'fixed_asset' ? 'selected' : '' }}>Long-term Fixed Asset (Depreciable)</option>
                                <option value="tool_stock" {{ old('accounting_treatment', $machine->accounting_treatment) == 'tool_stock' ? 'selected' : '' }}>Short-term Tool Stock (Inventory)</option>
                            </select>
                            @error('accounting_treatment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Tool Stock moves between <code>INV-TOOLS</code> and custody ledger when issued/returned.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Invoice Number</label>
                                <input type="text" name="purchase_invoice_no" class="form-control @error('purchase_invoice_no') is-invalid @enderror"
                                       value="{{ old('purchase_invoice_no', $machine->purchase_invoice_no) }}">
                                @error('purchase_invoice_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty (Months)</label>
                                <input type="number" name="warranty_months" class="form-control @error('warranty_months') is-invalid @enderror"
                                       value="{{ old('warranty_months', $machine->warranty_months) }}" min="0" max="120">
                                @error('warranty_months')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Warranty Expiry</label>
                            <input type="date" name="warranty_expiry_date" class="form-control"
                                   value="{{ old('warranty_expiry_date', $machine->warranty_expiry_date?->format('Y-m-d')) }}" readonly>
                            <small class="text-muted">Auto-calculated</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-6">
                <!-- Status & Location -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Status & Location</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
    <option value="active" {{ old('status', $machine->status) == 'active' ? 'selected' : '' }}>Active</option>
    <option value="under_maintenance" {{ old('status', $machine->status) == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
    <option value="breakdown" {{ old('status', $machine->status) == 'breakdown' ? 'selected' : '' }}>Breakdown</option>
    <option value="retired" {{ old('status', $machine->status) == 'retired' ? 'selected' : '' }}>Retired</option>
    <option value="disposed" {{ old('status', $machine->status) == 'disposed' ? 'selected' : '' }}>Disposed</option>
</select>

                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                                <option value="">No department</option>
                                @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id', $machine->department_id) == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('department_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Location</label>
                            <input type="text" name="current_location" class="form-control @error('current_location') is-invalid @enderror" 
                                   value="{{ old('current_location', $machine->current_location) }}" placeholder="e.g., Workshop, Site A">
                            @error('current_location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-2">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                                   {{ old('is_active', $machine->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                <strong>Active</strong>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Operating Hours -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Operating Hours</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Total Operating Hours</label>
                            <input type="number" name="operating_hours_total" step="0.01" 
                                   class="form-control @error('operating_hours_total') is-invalid @enderror" 
                                   value="{{ old('operating_hours_total', $machine->operating_hours_total) }}">
                            @error('operating_hours_total')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Current total hours on machine</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Last Service Hours</label>
                            <input type="number" name="last_service_hours" step="0.01" 
                                   class="form-control @error('last_service_hours') is-invalid @enderror" 
                                   value="{{ old('last_service_hours', $machine->last_service_hours) }}">
                            @error('last_service_hours')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Maintenance -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Maintenance Settings</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Maintenance Frequency (Days)</label>
                            <input type="number" name="maintenance_frequency_days" 
                                   class="form-control @error('maintenance_frequency_days') is-invalid @enderror" 
                                   value="{{ old('maintenance_frequency_days', $machine->maintenance_frequency_days) }}" 
                                   min="1" placeholder="e.g., 90 for quarterly">
                            @error('maintenance_frequency_days')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Last Maintenance Date</label>
                            <input type="date" name="last_maintenance_date" 
                                   class="form-control @error('last_maintenance_date') is-invalid @enderror" 
                                   value="{{ old('last_maintenance_date', $machine->last_maintenance_date?->format('Y-m-d')) }}">
                            @error('last_maintenance_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Next Maintenance Due</label>
                            <input type="date" name="next_maintenance_due_date" class="form-control" 
                                   value="{{ old('next_maintenance_due_date', $machine->next_maintenance_due_date?->format('Y-m-d')) }}" readonly>
                            <small class="text-muted">Auto-calculated from last date + frequency</small>
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
                                       {{ old('requires_calibration', $machine->requires_calibration) ? 'checked' : '' }}>
                                <label class="form-check-label" for="requires_calibration">
                                    <strong>Requires Calibration</strong>
                                </label>
                            </div>
                            <small class="text-muted">Check if this machine needs periodic calibration</small>
                        </div>

                        <div id="calibration_fields" style="display: none;">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> System will track calibration due dates and send alerts.
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Calibration Frequency</label>
                                    <div class="input-group">
                                        <input type="number" name="calibration_frequency_months" 
                                               class="form-control @error('calibration_frequency_months') is-invalid @enderror" 
                                               value="{{ old('calibration_frequency_months', $machine->calibration_frequency_months ?? 12) }}"
                                               min="1" max="60">
                                        <span class="input-group-text">months</span>
                                    </div>
                                    @error('calibration_frequency_months')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Calibration Agency</label>
                                    <input type="text" name="calibration_agency" 
                                           class="form-control @error('calibration_agency') is-invalid @enderror" 
                                           value="{{ old('calibration_agency', $machine->calibration_agency) }}">
                                    @error('calibration_agency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            @if($machine->last_calibration_date)
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Last Calibration</label>
                                    <input type="text" class="form-control" 
                                           value="{{ $machine->last_calibration_date->format('d-M-Y') }}" 
                                           readonly disabled>
                                    <small class="text-muted">Auto-updated</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Next Due</label>
                                    <input type="text" class="form-control" 
                                           value="{{ $machine->next_calibration_due_date?->format('d-M-Y') ?? 'Not set' }}" 
                                           readonly disabled>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="card mb-3">
                    <div class="card-header"><strong>Additional Information</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" 
                                      rows="3">{{ old('remarks', $machine->remarks) }}</textarea>
                            @error('remarks')
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
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> Update Machine
                </button>
                <a href="{{ route('machines.show', $machine) }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const requiresCalibration = document.getElementById('requires_calibration');
    const calibrationFields = document.getElementById('calibration_fields');
    
    function toggleCalibrationFields() {
        if (requiresCalibration.checked) {
            calibrationFields.style.display = 'block';
        } else {
            calibrationFields.style.display = 'none';
        }
    }
    
    requiresCalibration.addEventListener('change', toggleCalibrationFields);
    toggleCalibrationFields(); // Run on page load
});
</script>
@endpush
@endsection
