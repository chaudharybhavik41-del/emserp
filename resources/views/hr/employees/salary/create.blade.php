@extends('layouts.erp')

@section('title', 'Add Salary - ' . $employee->full_name)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">{{ $employee->currentSalary ? 'Revise' : 'Add' }} Salary</h1>
            <small class="text-muted">{{ $employee->employee_code }} - {{ $employee->full_name }}</small>
        </div>
        <a href="{{ route('hr.employees.salary.show', $employee) }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <form action="{{ route('hr.employees.salary.store', $employee) }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                {{-- Basic Info --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Salary Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Salary Structure</label>
                                <select name="hr_salary_structure_id" class="form-select" id="salaryStructure">
                                    <option value="">Custom / No Structure</option>
                                    @foreach($structures as $structure)
                                        <option value="{{ $structure->id }}" {{ old('hr_salary_structure_id') == $structure->id ? 'selected' : '' }}>
                                            {{ $structure->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Effective From <span class="text-danger">*</span></label>
                                <input type="date" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror" 
                                       value="{{ old('effective_from', date('Y-m-01')) }}" required>
                                @error('effective_from')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Earnings --}}
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i> Earnings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Basic Salary <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="basic" class="form-control @error('basic') is-invalid @enderror" 
                                           value="{{ old('basic', 0) }}" id="basic" min="0" step="0.01" required>
                                </div>
                                @error('basic')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">HRA</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="hra" class="form-control earning-field" 
                                           value="{{ old('hra', 0) }}" id="hra" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">DA</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="da" class="form-control earning-field" 
                                           value="{{ old('da', 0) }}" id="da" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Special Allowance</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="special_allowance" class="form-control earning-field" 
                                           value="{{ old('special_allowance', 0) }}" id="special_allowance" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Conveyance</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="conveyance" class="form-control earning-field" 
                                           value="{{ old('conveyance', 0) }}" id="conveyance" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Medical</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="medical" class="form-control earning-field" 
                                           value="{{ old('medical', 0) }}" id="medical" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Other Allowances</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" name="other_allowances" class="form-control earning-field" 
                                           value="{{ old('other_allowances', 0) }}" id="other_allowances" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statutory Settings --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-bank me-1"></i> Statutory Applicability</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pf_applicable" value="1" id="pfApplicable"
                                           {{ old('pf_applicable', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="pfApplicable">PF Applicable</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="esi_applicable" value="1" id="esiApplicable"
                                           {{ old('esi_applicable', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="esiApplicable">ESI Applicable</label>
                                </div>
                                <small class="text-muted">Auto-disabled if Gross > ₹21,000</small>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pt_applicable" value="1" id="ptApplicable"
                                           {{ old('pt_applicable', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ptApplicable">Professional Tax</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tds_applicable" value="1" id="tdsApplicable"
                                           {{ old('tds_applicable') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tdsApplicable">TDS Applicable</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="lwf_applicable" value="1" id="lwfApplicable"
                                           {{ old('lwf_applicable') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="lwfApplicable">LWF Applicable</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Revision Reason --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Remarks</h6>
                    </div>
                    <div class="card-body">
                        <textarea name="revision_reason" class="form-control" rows="3" 
                                  placeholder="Reason for salary revision (e.g., Annual increment, Promotion)">{{ old('revision_reason') }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('hr.employees.salary.show', $employee) }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Salary
                    </button>
                </div>
            </div>

            {{-- Summary Sidebar --}}
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 80px;">
                    <div class="card-header">
                        <h6 class="mb-0">Salary Preview</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr class="table-success">
                                    <td><strong>Gross Salary</strong></td>
                                    <td class="text-end"><strong id="previewGross">₹0</strong></td>
                                </tr>
                                <tr>
                                    <td>PF (Employee 12%)</td>
                                    <td class="text-end text-danger" id="previewPfEe">₹0</td>
                                </tr>
                                <tr>
                                    <td>ESI (Employee 0.75%)</td>
                                    <td class="text-end text-danger" id="previewEsiEe">₹0</td>
                                </tr>
                                <tr>
                                    <td>Professional Tax</td>
                                    <td class="text-end text-danger" id="previewPt">₹0</td>
                                </tr>
                                <tr class="table-warning">
                                    <td><strong>Total Deductions</strong></td>
                                    <td class="text-end text-danger"><strong id="previewDeductions">₹0</strong></td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>Net Salary</strong></td>
                                    <td class="text-end"><strong id="previewNet">₹0</strong></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><hr class="my-2"></td>
                                </tr>
                                <tr>
                                    <td>PF (Employer)</td>
                                    <td class="text-end text-info" id="previewPfEr">₹0</td>
                                </tr>
                                <tr>
                                    <td>ESI (Employer)</td>
                                    <td class="text-end text-info" id="previewEsiEr">₹0</td>
                                </tr>
                                <tr class="table-info">
                                    <td><strong>CTC (Monthly)</strong></td>
                                    <td class="text-end"><strong id="previewCtc">₹0</strong></td>
                                </tr>
                                <tr class="table-secondary">
                                    <td><strong>CTC (Annual)</strong></td>
                                    <td class="text-end"><strong id="previewCtcAnnual">₹0</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const earningFields = document.querySelectorAll('.earning-field, #basic');
    const pfCheckbox = document.getElementById('pfApplicable');
    const esiCheckbox = document.getElementById('esiApplicable');
    const ptCheckbox = document.getElementById('ptApplicable');

    function calculateSalary() {
        // Get all earnings
        const basic = parseFloat(document.getElementById('basic').value) || 0;
        const hra = parseFloat(document.getElementById('hra').value) || 0;
        const da = parseFloat(document.getElementById('da').value) || 0;
        const special = parseFloat(document.getElementById('special_allowance').value) || 0;
        const conv = parseFloat(document.getElementById('conveyance').value) || 0;
        const medical = parseFloat(document.getElementById('medical').value) || 0;
        const other = parseFloat(document.getElementById('other_allowances').value) || 0;

        const gross = basic + hra + da + special + conv + medical + other;

        // Calculate deductions
        let pfEe = 0, pfEr = 0;
        if (pfCheckbox.checked) {
            pfEe = Math.min(basic * 0.12, 1800);
            pfEr = Math.min(basic * 0.12, 1800);
        }

        let esiEe = 0, esiEr = 0;
        if (esiCheckbox.checked && gross <= 21000) {
            esiEe = gross * 0.0075;
            esiEr = gross * 0.0325;
        }

        let pt = 0;
        if (ptCheckbox.checked) {
            pt = 200; // Simplified - should use actual slabs
        }

        const totalDeductions = pfEe + esiEe + pt;
        const net = gross - totalDeductions;
        const ctc = gross + pfEr + esiEr;

        // Update preview
        document.getElementById('previewGross').textContent = '₹' + formatNumber(gross);
        document.getElementById('previewPfEe').textContent = '₹' + formatNumber(pfEe);
        document.getElementById('previewEsiEe').textContent = '₹' + formatNumber(esiEe);
        document.getElementById('previewPt').textContent = '₹' + formatNumber(pt);
        document.getElementById('previewDeductions').textContent = '₹' + formatNumber(totalDeductions);
        document.getElementById('previewNet').textContent = '₹' + formatNumber(net);
        document.getElementById('previewPfEr').textContent = '₹' + formatNumber(pfEr);
        document.getElementById('previewEsiEr').textContent = '₹' + formatNumber(esiEr);
        document.getElementById('previewCtc').textContent = '₹' + formatNumber(ctc);
        document.getElementById('previewCtcAnnual').textContent = '₹' + formatNumber(ctc * 12);

        // Auto-disable ESI if gross > 21000
        if (gross > 21000) {
            esiCheckbox.checked = false;
            esiCheckbox.disabled = true;
        } else {
            esiCheckbox.disabled = false;
        }
    }

    function formatNumber(num) {
        return Math.round(num).toLocaleString('en-IN');
    }

    // Bind events
    earningFields.forEach(field => {
        field.addEventListener('input', calculateSalary);
    });
    pfCheckbox.addEventListener('change', calculateSalary);
    esiCheckbox.addEventListener('change', calculateSalary);
    ptCheckbox.addEventListener('change', calculateSalary);

    // Initial calculation
    calculateSalary();
});
</script>
@endpush
@endsection
