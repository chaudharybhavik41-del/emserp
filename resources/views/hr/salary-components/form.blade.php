@extends('layouts.erp')

@section('title', isset($component) ? 'Edit Salary Component' : 'Add Salary Component')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($component) ? 'Edit Salary Component' : 'Add Salary Component' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.salary-components.index') }}">Salary Components</a></li>
                <li class="breadcrumb-item active">{{ isset($component) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    @include('partials.flash')

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" 
                          action="{{ isset($component) ? route('hr.salary-components.update', $component) : route('hr.salary-components.store') }}">
                        @csrf
                        @if(isset($component))
                            @method('PUT')
                        @endif

                        <h6 class="border-bottom pb-2 mb-3">Basic Information</h6>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $component->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-5">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $component->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="short_name" class="form-label">Short Name</label>
                                <input type="text" class="form-control @error('short_name') is-invalid @enderror" 
                                       id="short_name" name="short_name" 
                                       value="{{ old('short_name', $component->short_name ?? '') }}" 
                                       maxlength="20">
                                @error('short_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="component_type" class="form-label">Component Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('component_type') is-invalid @enderror" 
                                        id="component_type" name="component_type" required>
                                    <option value="">Select Type</option>
                                    <option value="earning" {{ old('component_type', $component->component_type ?? '') == 'earning' ? 'selected' : '' }}>Earning</option>
                                    <option value="deduction" {{ old('component_type', $component->component_type ?? '') == 'deduction' ? 'selected' : '' }}>Deduction</option>
                                    <option value="employer_contribution" {{ old('component_type', $component->component_type ?? '') == 'employer_contribution' ? 'selected' : '' }}>Employer Contribution</option>
                                </select>
                                @error('component_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select @error('category') is-invalid @enderror" 
                                        id="category" name="category">
                                    <option value="">Select Category</option>
                                    <option value="basic" {{ old('category', $component->category ?? '') == 'basic' ? 'selected' : '' }}>Basic</option>
                                    <option value="hra" {{ old('category', $component->category ?? '') == 'hra' ? 'selected' : '' }}>HRA</option>
                                    <option value="da" {{ old('category', $component->category ?? '') == 'da' ? 'selected' : '' }}>DA</option>
                                    <option value="conveyance" {{ old('category', $component->category ?? '') == 'conveyance' ? 'selected' : '' }}>Conveyance</option>
                                    <option value="medical" {{ old('category', $component->category ?? '') == 'medical' ? 'selected' : '' }}>Medical</option>
                                    <option value="special_allowance" {{ old('category', $component->category ?? '') == 'special_allowance' ? 'selected' : '' }}>Special Allowance</option>
                                    <option value="overtime" {{ old('category', $component->category ?? '') == 'overtime' ? 'selected' : '' }}>Overtime</option>
                                    <option value="bonus" {{ old('category', $component->category ?? '') == 'bonus' ? 'selected' : '' }}>Bonus</option>
                                    <option value="pf" {{ old('category', $component->category ?? '') == 'pf' ? 'selected' : '' }}>Provident Fund</option>
                                    <option value="esi" {{ old('category', $component->category ?? '') == 'esi' ? 'selected' : '' }}>ESI</option>
                                    <option value="professional_tax" {{ old('category', $component->category ?? '') == 'professional_tax' ? 'selected' : '' }}>Professional Tax</option>
                                    <option value="tds" {{ old('category', $component->category ?? '') == 'tds' ? 'selected' : '' }}>TDS</option>
                                    <option value="loan" {{ old('category', $component->category ?? '') == 'loan' ? 'selected' : '' }}>Loan</option>
                                    <option value="gratuity" {{ old('category', $component->category ?? '') == 'gratuity' ? 'selected' : '' }}>Gratuity</option>
                                    <option value="other" {{ old('category', $component->category ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Calculation</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="calculation_type" class="form-label">Calculation Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('calculation_type') is-invalid @enderror" 
                                        id="calculation_type" name="calculation_type" required onchange="toggleCalculationFields()">
                                    <option value="">Select Type</option>
                                    <option value="fixed" {{ old('calculation_type', $component->calculation_type ?? '') == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                    <option value="percent_of_basic" {{ old('calculation_type', $component->calculation_type ?? '') == 'percent_of_basic' ? 'selected' : '' }}>Percentage of Basic</option>
                                    <option value="percent_of_gross" {{ old('calculation_type', $component->calculation_type ?? '') == 'percent_of_gross' ? 'selected' : '' }}>Percentage of Gross</option>
                                    <option value="percent_of_ctc" {{ old('calculation_type', $component->calculation_type ?? '') == 'percent_of_ctc' ? 'selected' : '' }}>Percentage of CTC</option>
                                    <option value="attendance_based" {{ old('calculation_type', $component->calculation_type ?? '') == 'attendance_based' ? 'selected' : '' }}>Attendance Based</option>
                                    <option value="slab_based" {{ old('calculation_type', $component->calculation_type ?? '') == 'slab_based' ? 'selected' : '' }}>Slab Based</option>
                                    <option value="formula" {{ old('calculation_type', $component->calculation_type ?? '') == 'formula' ? 'selected' : '' }}>Formula</option>
                                </select>
                                @error('calculation_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3" id="fixedAmountField">
                                <label for="default_value" class="form-label">Default Amount (₹)</label>
                                <input type="number" class="form-control @error('default_value') is-invalid @enderror" 
                                       id="default_value" name="default_value" 
                                       value="{{ old('default_value', $component->default_value ?? '') }}" 
                                       min="0" step="0.01">
                                @error('default_value')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3" id="percentageField" style="display: none;">
                                <label for="percentage" class="form-label">Percentage (%)</label>
                                <input type="number" class="form-control @error('percentage') is-invalid @enderror" 
                                       id="percentage" name="percentage" 
                                       value="{{ old('percentage', $component->percentage ?? '') }}" 
                                       min="0" max="100" step="0.01">
                                @error('percentage')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3" id="formulaField" style="display: none;">
                            <label for="formula" class="form-label">Formula</label>
                            <textarea class="form-control @error('formula') is-invalid @enderror" 
                                      id="formula" name="formula" 
                                      rows="2" maxlength="500"
                                      placeholder="e.g., GROSS - BASIC - HRA - DA">{{ old('formula', $component->formula ?? '') }}</textarea>
                            @error('formula')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Use component codes: BASIC, HRA, DA, GROSS, CTC</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="2" maxlength="500">{{ old('description', $component->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Tax & Statutory</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="is_taxable" 
                                           name="is_taxable" value="1"
                                           {{ old('is_taxable', $component->is_taxable ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_taxable">Taxable</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="is_statutory" 
                                           name="is_statutory" value="1"
                                           {{ old('is_statutory', $component->is_statutory ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_statutory">Statutory Component</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="affects_pf" 
                                           name="affects_pf" value="1"
                                           {{ old('affects_pf', $component->affects_pf ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="affects_pf">Affects PF Calculation</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="affects_esi" 
                                           name="affects_esi" value="1"
                                           {{ old('affects_esi', $component->affects_esi ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="affects_esi">Affects ESI Calculation</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="affects_gratuity" 
                                           name="affects_gratuity" value="1"
                                           {{ old('affects_gratuity', $component->affects_gratuity ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="affects_gratuity">Affects Gratuity Calculation</label>
                                </div>
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Display Options</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                       id="sort_order" name="sort_order" 
                                       value="{{ old('sort_order', $component->sort_order ?? '') }}" 
                                       min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label d-block">&nbsp;</label>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_part_of_ctc" 
                                           name="is_part_of_ctc" value="1"
                                           {{ old('is_part_of_ctc', $component->is_part_of_ctc ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_part_of_ctc">Part of CTC</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_part_of_gross" 
                                           name="is_part_of_gross" value="1"
                                           {{ old('is_part_of_gross', $component->is_part_of_gross ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_part_of_gross">Part of Gross</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="show_in_payslip" 
                                           name="show_in_payslip" value="1"
                                           {{ old('show_in_payslip', $component->show_in_payslip ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_in_payslip">Show in Payslip</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="show_if_zero" 
                                           name="show_if_zero" value="1"
                                           {{ old('show_if_zero', $component->show_if_zero ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_if_zero">Show if Zero</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_active" 
                                   name="is_active" value="1"
                                   {{ old('is_active', $component->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($component) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('hr.salary-components.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Help</h6>
                </div>
                <div class="card-body small">
                    <p class="text-muted mb-2"><strong>Component Types:</strong></p>
                    <ul class="mb-3">
                        <li><span class="badge bg-success">Earning</span> - Adds to salary (Basic, HRA, etc.)</li>
                        <li><span class="badge bg-danger">Deduction</span> - Deducts from salary (PF, ESI, etc.)</li>
                        <li><span class="badge bg-info">Employer</span> - Employer's contribution (not in net pay)</li>
                    </ul>
                    <p class="text-muted mb-2"><strong>Calculation Types:</strong></p>
                    <ul class="mb-0">
                        <li><strong>Fixed</strong> - Same amount every month</li>
                        <li><strong>% of Basic</strong> - Calculated on basic salary</li>
                        <li><strong>% of Gross</strong> - Calculated on gross salary</li>
                        <li><strong>% of CTC</strong> - Calculated on total CTC</li>
                        <li><strong>Attendance Based</strong> - Pro-rated by days worked</li>
                        <li><strong>Slab Based</strong> - Uses predefined slabs (like PT)</li>
                        <li><strong>Formula</strong> - Custom calculation formula</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleCalculationFields() {
    const calcType = document.getElementById('calculation_type').value;
    const fixedField = document.getElementById('fixedAmountField');
    const percentageField = document.getElementById('percentageField');
    const formulaField = document.getElementById('formulaField');
    
    // Show/hide based on calculation type
    if (calcType === 'fixed' || calcType === 'attendance_based') {
        fixedField.style.display = 'block';
        percentageField.style.display = 'none';
        formulaField.style.display = 'none';
    } else if (calcType.startsWith('percent_of_')) {
        fixedField.style.display = 'none';
        percentageField.style.display = 'block';
        formulaField.style.display = 'none';
    } else if (calcType === 'formula') {
        fixedField.style.display = 'none';
        percentageField.style.display = 'none';
        formulaField.style.display = 'block';
    } else {
        fixedField.style.display = 'block';
        percentageField.style.display = 'none';
        formulaField.style.display = 'none';
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleCalculationFields();
});
</script>
@endpush
@endsection
