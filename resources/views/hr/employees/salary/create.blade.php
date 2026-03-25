@extends('layouts.erp')

@section('title', 'Add Salary - ' . $employee->full_name)

@section('content')
    <div class="container-fluid">
        @php
            $currentSalary = $employee->currentSalary;
            $structurePayload = $structures->mapWithKeys(function ($structure) {
                return [
                    $structure->id => [
                        'id' => $structure->id,
                        'name' => $structure->name,
                        'base_type' => $structure->base_type,
                        'components' => $structure->components
                            ->sortBy(fn($component) => $component->pivot->sort_order ?? $component->sort_order ?? 999)
                            ->map(fn($component) => [
                                'id' => $component->id,
                                'code' => $component->code,
                                'category' => $component->category,
                                'component_type' => $component->component_type,
                                'calculation_type' => $component->pivot->calculation_type ?? $component->calculation_type,
                                'value' => (float) ($component->pivot->value ?? $component->default_value ?? 0),
                                'percentage' => (float) ($component->pivot->percentage ?? $component->percentage ?? 0),
                            ])->values()->all(),
                    ]
                ];
            });
        @endphp

        @include('hr.employees.partials.hub-nav', ['employee' => $employee, 'activeSection' => 'salary'])

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
                                                {{ $structure->name }} ({{ $structure->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Selecting a structure will apply its rules to the salary
                                        fields. You can still adjust the values before saving.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Effective From <span class="text-danger">*</span></label>
                                    <input type="date" name="effective_from"
                                        class="form-control @error('effective_from') is-invalid @enderror"
                                        value="{{ old('effective_from', date('Y-m-01')) }}" required>
                                    @error('effective_from')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Selected Structure Breakdown</h6>
                            <span class="badge bg-secondary" id="structurePreviewCount">0 components</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-light border mb-0" id="structurePreviewEmpty">
                                Select a salary structure to view all component rules and calculated monthly amounts.
                            </div>
                            <div class="table-responsive d-none" id="structurePreviewTableWrap">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Component</th>
                                            <th>Type</th>
                                            <th>Rule</th>
                                            <th class="text-center">Applicable</th>
                                            <th class="text-end">Monthly Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="structurePreviewTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Earnings --}}
                    <div class="card mb-4" id="earningsCard">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i> Earnings</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Basic Salary <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="basic"
                                            class="form-control @error('basic') is-invalid @enderror"
                                            value="{{ old('basic', $currentSalary?->basic ?? 0) }}" id="basic" min="0"
                                            step="0.01" required>
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
                                            value="{{ old('hra', $currentSalary?->hra ?? 0) }}" id="hra" min="0"
                                            step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">DA</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="da" class="form-control earning-field"
                                            value="{{ old('da', $currentSalary?->da ?? 0) }}" id="da" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Special Allowance</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="special_allowance" class="form-control earning-field"
                                            value="{{ old('special_allowance', $currentSalary?->special_allowance ?? 0) }}"
                                            id="special_allowance" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Conveyance</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="conveyance" class="form-control earning-field"
                                            value="{{ old('conveyance', $currentSalary?->conveyance ?? 0) }}"
                                            id="conveyance" min="0" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Medical</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="medical" class="form-control earning-field"
                                            value="{{ old('medical', $currentSalary?->medical ?? 0) }}" id="medical" min="0"
                                            step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Other Allowances</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" name="other_allowances" class="form-control earning-field"
                                            value="{{ old('other_allowances', $currentSalary?->other_allowances ?? 0) }}"
                                            id="other_allowances" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Statutory Settings --}}
                    <div class="card mb-4" id="statutoryCard">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-bank me-1"></i> Statutory Applicability</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-light border py-2 small d-none" id="structureDerivedNotice">
                                Basic earnings and PF / ESI / PT applicability are being derived from the selected structure
                                rows below.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pf_applicable" value="1"
                                            id="pfApplicable" {{ old('pf_applicable', $currentSalary?->pf_applicable ?? $employee->pf_applicable ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="pfApplicable">PF Applicable</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="esi_applicable" value="1"
                                            id="esiApplicable" {{ old('esi_applicable', $currentSalary?->esi_applicable ?? $employee->esi_applicable ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="esiApplicable">ESI Applicable</label>
                                    </div>
                                    <small class="text-muted">Auto-disabled if Gross > ₹21,000</small>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pt_applicable" value="1"
                                            id="ptApplicable" {{ old('pt_applicable', $currentSalary?->pt_applicable ?? $employee->pt_applicable ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ptApplicable">Professional Tax</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="tds_applicable" value="1"
                                            id="tdsApplicable" {{ old('tds_applicable', $employee->tds_applicable ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="tdsApplicable">TDS Applicable</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="lwf_applicable" value="1"
                                            id="lwfApplicable" {{ old('lwf_applicable', $employee->lwf_applicable ?? false) ? 'checked' : '' }}>
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
                                placeholder="Reason for salary revision (e.g., Annual increment, Promotion)">{{ old('revision_reason', $currentSalary ? 'Salary revision' : '') }}</textarea>
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
                                        <td colspan="2">
                                            <hr class="my-2">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PF ER (Employer 3.67%)</td>
                                        <td class="text-end text-info" id="previewPfEr">₹0</td>
                                    </tr>
                                    <tr>
                                        <td>EPS (Employer 8.33%)</td>
                                        <td class="text-end text-info" id="previewEps">₹0</td>
                                    </tr>
                                    <tr>
                                        <td>Employer PF Total</td>
                                        <td class="text-end text-info" id="previewEmployerPfTotal">₹0</td>
                                    </tr>
                                    <tr>
                                        <td>EDLI (Employer 0.50%)</td>
                                        <td class="text-end text-info" id="previewEdli">₹0</td>
                                    </tr>
                                    <tr>
                                        <td>PF Admin Charges</td>
                                        <td class="text-end text-info" id="previewPfAdmin">₹0</td>
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
            document.addEventListener('DOMContentLoaded', function () {
                const structureSelect = document.getElementById('salaryStructure');
                const effectiveFromInput = document.querySelector('input[name="effective_from"]');
                const earningsCard = document.getElementById('earningsCard');
                const structureDerivedNotice = document.getElementById('structureDerivedNotice');
                const earningFields = document.querySelectorAll('.earning-field, #basic');
                const pfCheckbox = document.getElementById('pfApplicable');
                const esiCheckbox = document.getElementById('esiApplicable');
                const ptCheckbox = document.getElementById('ptApplicable');
                const structurePreviewEmpty = document.getElementById('structurePreviewEmpty');
                const structurePreviewTableWrap = document.getElementById('structurePreviewTableWrap');
                const structurePreviewTableBody = document.getElementById('structurePreviewTableBody');
                const structurePreviewCount = document.getElementById('structurePreviewCount');
                const structurePayload = @json($structurePayload);
                const ptSlabPayload = @json($ptSlabPayload);
                const employeePtState = @json($employee->pt_state);
                const employeeGender = @json($employee->gender);
                let structureComponentOverrides = {};

                const fieldMap = {
                    basic: 'basic',
                    hra: 'hra',
                    da: 'da',
                    special_allowance: 'special_allowance',
                    conveyance: 'conveyance',
                    medical: 'medical',
                    other_earning: 'other_allowances',
                };

                const aliases = {
                    basic: ['BASIC'],
                    hra: ['HRA'],
                    da: ['DA'],
                    special_allowance: ['SPECIAL', 'SPL'],
                    conveyance: ['CONV'],
                    medical: ['MED'],
                    other_earning: ['OTH', 'OTHER'],
                    pf_employee: ['PF_EE', 'PFEE'],
                    esi_employee: ['ESI_EE', 'ESIEE'],
                    professional_tax: ['PT'],
                };

                function componentMatches(component, category) {
                    return component.category === category || (aliases[category] || []).includes(component.code);
                }

                function matchesEmployeeState(slab) {
                    const employeeState = String(employeePtState || '').trim().toLowerCase();

                    if (!employeeState) {
                        return true; // Fallback to any state if employee has no state assigned
                    }

                    return employeeState === String(slab.state_code || '').trim().toLowerCase()
                        || employeeState === String(slab.state_name || '').trim().toLowerCase();
                }

                function matchesEmployeeGender(slab) {
                    const slabGender = String(slab.gender || 'all').trim().toLowerCase();
                    const currentGender = String(employeeGender || '').trim().toLowerCase();

                    return slabGender === 'all' || !currentGender || slabGender === currentGender;
                }

                function currentEffectiveDate() {
                    return effectiveFromInput?.value || '{{ date('Y-m-01') }}';
                }

                function professionalTaxFromSlab(gross) {
                    const effectiveDate = currentEffectiveDate();
                    const match = (ptSlabPayload || []).find((slab) => {
                        const startsOnOrBefore = !slab.effective_from || slab.effective_from <= effectiveDate;
                        const endsOnOrAfter = !slab.effective_to || slab.effective_to >= effectiveDate;
                        const upperBound = (!slab.salary_to || Number(slab.salary_to) === 0) ? Infinity : Number(slab.salary_to);

                        return startsOnOrBefore
                            && endsOnOrAfter
                            && matchesEmployeeState(slab)
                            && matchesEmployeeGender(slab)
                            && gross >= Number(slab.salary_from || 0)
                            && gross <= upperBound;
                    });

                    return Number(match?.tax_amount || 0);
                }

                function calculateComponentAmount(component, basic, gross) {
                    if (componentMatches(component, 'professional_tax')) {
                        return professionalTaxFromSlab(gross);
                    }

                    if (componentMatches(component, 'pf_employee')) {
                        const pfWageBase = Math.min(basic, 15000); // Standard PF Wage Ceiling
                        const percentage = parseFloat(component.percentage || 12);
                        return pfWageBase * (percentage / 100);
                    }

                    if (componentMatches(component, 'esi_employee')) {
                        if (gross > 21000) return 0;
                        const percentage = parseFloat(component.percentage || 0.75);
                        return gross * (percentage / 100);
                    }

                    const calcType = component.calculation_type || 'fixed';
                    const value = parseFloat(component.value || 0);
                    const percentage = parseFloat(component.percentage || 0);

                    if (calcType === 'fixed') {
                        return value;
                    }

                    if (calcType === 'percent_of_basic') {
                        return basic * (percentage / 100);
                    }

                    if (calcType === 'percent_of_gross') {
                        return gross * (percentage / 100);
                    }

                    if (calcType === 'slab_based' && componentMatches(component, 'professional_tax')) {
                        return professionalTaxFromSlab(gross);
                    }

                    return 0;
                }

                function componentTypeTone(type) {
                    if (type === 'earning') return 'success';
                    if (type === 'deduction') return 'danger';
                    return 'info';
                }

                function componentRuleText(component) {
                    const calcType = component.calculation_type || 'fixed';

                    if (calcType.startsWith('percent_')) {
                        return `${formatNumber(component.percentage || 0)}% of ${calcType.replace('percent_of_', '').replaceAll('_', ' ')}`;
                    }

                    if (calcType === 'formula') {
                        return component.formula || 'Formula';
                    }

                    return calcType.replaceAll('_', ' ');
                }

                function renderStructureBreakdown(skipCapture = false) {
                    const structureId = structureSelect.value;
                    const structure = structurePayload[structureId];

                    if (!structure) {
                        structurePreviewEmpty.classList.remove('d-none');
                        structurePreviewTableWrap.classList.add('d-none');
                        structurePreviewTableBody.innerHTML = '';
                        structurePreviewCount.textContent = '0 components';
                        return;
                    }

                    const basic = parseFloat(document.getElementById('basic').value) || 0;
                    const hra = parseFloat(document.getElementById('hra').value) || 0;
                    const da = parseFloat(document.getElementById('da').value) || 0;
                    const special = parseFloat(document.getElementById('special_allowance').value) || 0;
                    const conv = parseFloat(document.getElementById('conveyance').value) || 0;
                    const medical = parseFloat(document.getElementById('medical').value) || 0;
                    const other = parseFloat(document.getElementById('other_allowances').value) || 0;
                    const gross = basic + hra + da + special + conv + medical + other;

                    if (!skipCapture) {
                        captureStructureOverrides();
                    }

                    let html = '';

                    const renderComponentRow = (component) => {
                        const override = structureComponentOverrides[String(component.id)] || {};
                        const originalAmount = calculateComponentAmount(component, basic, gross);
                        const applicable = override.applicable !== undefined ? override.applicable : (originalAmount > 0 || component.is_mandatory);

                        let amount = override.monthly_amount;
                        if (amount === undefined || override.force_recalc) {
                            amount = applicable ? originalAmount : 0;
                        } else if (!applicable) {
                            amount = 0;
                        }

                        return `
                                <tr>
                                    <td>
                                        <div class="fw-semibold">${component.code}</div>
                                        <div class="small text-muted">${component.category || '-'}</div>
                                    </td>
                                    <td><span class="badge bg-${componentTypeTone(component.component_type)}">${component.component_type.replaceAll('_', ' ')}</span></td>
                                    <td>${componentRuleText(component)}</td>
                                    <td class="text-center">
                                        <input type="hidden" name="structure_components[${component.id}][hr_salary_component_id]" value="${component.id}">
                                        <input type="hidden" name="structure_components[${component.id}][calculation_type]" value="${component.calculation_type || 'fixed'}">
                                        <input type="hidden" name="structure_components[${component.id}][percentage]" value="${component.percentage || 0}">
                                        <input type="hidden" name="structure_components[${component.id}][applicable]" value="0">
                                        <input class="form-check-input component-applicable-input" type="checkbox"
                                               name="structure_components[${component.id}][applicable]" value="1"
                                               data-component-id="${component.id}" data-category="${component.category || ''}"
                                               ${applicable ? 'checked' : ''}>
                                    </td>
                                    <td class="text-end">
                                        <input type="number" step="0.01" min="0"
                                               class="form-control form-control-sm text-end component-amount-input"
                                               name="structure_components[${component.id}][monthly_amount]"
                                               value="${Number(amount).toFixed(2)}"
                                               data-component-id="${component.id}" data-category="${component.category || ''}"
                                               ${!applicable ? 'readonly' : ''}>
                                    </td>
                                </tr>
                            `;
                    };

                    const earnings = structure.components.filter(c => c.component_type === 'earning');
                    const deductions = structure.components.filter(c => c.component_type === 'deduction');
                    const employers = structure.components.filter(c => c.component_type === 'employer_contribution');

                    if (earnings.length > 0) {
                        html += `<tr class="table-light"><td colspan="5"><strong>1. Earnings</strong></td></tr>`;
                        html += earnings.map(renderComponentRow).join('');
                    }
                    if (deductions.length > 0) {
                        html += `<tr class="table-light"><td colspan="5"><strong>2. Deductions</strong></td></tr>`;
                        html += deductions.map(renderComponentRow).join('');
                    }
                    if (employers.length > 0) {
                        html += `<tr class="table-light"><td colspan="5"><strong>3. Employer Contributions</strong></td></tr>`;
                        html += employers.map(renderComponentRow).join('');
                    }

                    structurePreviewTableBody.innerHTML = html;
                    structurePreviewEmpty.classList.add('d-none');
                    structurePreviewTableWrap.classList.remove('d-none');
                    structurePreviewCount.textContent = `${structure.components.length} components`;
                }

                function captureStructureOverrides() {
                    structurePreviewTableBody.querySelectorAll('tr').forEach(row => {
                        const checkbox = row.querySelector('.component-applicable-input');
                        const amountInput = row.querySelector('.component-amount-input');

                        if (!checkbox || !amountInput) {
                            return;
                        }

                        const id = String(checkbox.dataset.componentId);
                        if (!structureComponentOverrides[id]) structureComponentOverrides[id] = {};

                        structureComponentOverrides[id].applicable = checkbox.checked;
                        structureComponentOverrides[id].monthly_amount = parseFloat(amountInput.value || 0) || 0;
                        structureComponentOverrides[id].force_recalc = false; // Reset force recalc
                    });
                }

                function syncStructureRowToPrimaryInputs(category, amount, applicable) {
                    const fieldId = fieldMap[category];

                    if (fieldId && document.getElementById(fieldId)) {
                        document.getElementById(fieldId).value = applicable ? Number(amount).toFixed(2) : '0.00';
                    }

                    if (category === 'pf_employee') {
                        pfCheckbox.checked = applicable;
                    }

                    if (category === 'esi_employee') {
                        esiCheckbox.checked = applicable;
                    }

                    if (category === 'professional_tax') {
                        ptCheckbox.checked = applicable;
                    }
                }

                function syncUiMode() {
                    const hasStructure = Boolean(structureSelect.value);

                    earningsCard.classList.toggle('d-none', hasStructure);
                    structureDerivedNotice.classList.toggle('d-none', !hasStructure);
                    pfCheckbox.disabled = hasStructure;
                    esiCheckbox.disabled = hasStructure;
                    ptCheckbox.disabled = hasStructure;
                }

                function applyStructure() {
                    const structureId = structureSelect.value;

                    if (!structureId || !structurePayload[structureId]) {
                        calculateSalary();
                        renderStructureBreakdown();
                        return;
                    }

                    const structure = structurePayload[structureId];
                    structureComponentOverrides = {};
                    const components = structure.components || [];
                    let basic = parseFloat(document.getElementById('basic').value) || 0;

                    const basicComponent = components.find(component => componentMatches(component, 'basic'));
                    if (basicComponent) {
                        const basicAmount = calculateComponentAmount(basicComponent, basic, basic);
                        if (basicAmount > 0) {
                            basic = basicAmount;
                            document.getElementById('basic').value = basic.toFixed(2);
                        }
                    }

                    for (const [category, fieldId] of Object.entries(fieldMap)) {
                        if (category === 'basic') {
                            continue;
                        }

                        const component = components.find(item => componentMatches(item, category));
                        const field = document.getElementById(fieldId);

                        if (!field) {
                            continue;
                        }

                        if (!component) {
                            field.value = '0.00';
                            continue;
                        }

                        const grossSeed = basic;
                        const amount = calculateComponentAmount(component, basic, grossSeed);
                        field.value = amount.toFixed(2);
                    }

                    pfCheckbox.checked = components.some(component => componentMatches(component, 'pf_employee'));
                    esiCheckbox.checked = components.some(component => componentMatches(component, 'esi_employee'));
                    ptCheckbox.checked = components.some(component => componentMatches(component, 'professional_tax'));

                    calculateSalary();
                    renderStructureBreakdown();
                    syncUiMode();
                }

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
                    let pfEe = 0, pfEr = 0, eps = 0, edli = 0, pfAdmin = 0, employerPfTotal = 0;
                    if (pfCheckbox.checked) {
                        const pfWageBase = Math.min(basic, 15000);
                        pfEe = pfWageBase * 0.12;
                        eps = pfWageBase * 0.0833;
                        employerPfTotal = pfWageBase * 0.12;
                        pfEr = Math.max(0, employerPfTotal - eps);
                        edli = pfWageBase * 0.005;
                        pfAdmin = pfWageBase * 0.005;
                    }

                    let esiEe = 0, esiEr = 0;
                    if (esiCheckbox.checked && gross <= 21000) {
                        esiEe = gross * 0.0075;
                        esiEr = gross * 0.0325;
                    }

                    let pt = 0;
                    if (ptCheckbox.checked) {
                        pt = professionalTaxFromSlab(gross);
                    }

                    const totalDeductions = pfEe + esiEe + pt;
                    const net = gross - totalDeductions;
                    const ctc = gross + employerPfTotal + esiEr;

                    // Update preview
                    document.getElementById('previewGross').textContent = '₹' + formatNumber(gross);
                    document.getElementById('previewPfEe').textContent = '₹' + formatNumber(pfEe);
                    document.getElementById('previewEsiEe').textContent = '₹' + formatNumber(esiEe);
                    document.getElementById('previewPt').textContent = '₹' + formatNumber(pt);
                    document.getElementById('previewDeductions').textContent = '₹' + formatNumber(totalDeductions);
                    document.getElementById('previewNet').textContent = '₹' + formatNumber(net);
                    document.getElementById('previewPfEr').textContent = '₹' + formatNumber(pfEr);
                    document.getElementById('previewEps').textContent = '₹' + formatNumber(eps);
                    document.getElementById('previewEmployerPfTotal').textContent = '₹' + formatNumber(employerPfTotal);
                    document.getElementById('previewEdli').textContent = '₹' + formatNumber(edli);
                    document.getElementById('previewPfAdmin').textContent = '₹' + formatNumber(pfAdmin);
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
                    field.addEventListener('input', () => {
                        calculateSalary();
                        renderStructureBreakdown();
                    });
                });
                pfCheckbox.addEventListener('change', () => {
                    calculateSalary();
                    renderStructureBreakdown();
                });
                esiCheckbox.addEventListener('change', () => {
                    calculateSalary();
                    renderStructureBreakdown();
                });
                ptCheckbox.addEventListener('change', () => {
                    calculateSalary();
                    renderStructureBreakdown();
                });
                effectiveFromInput?.addEventListener('change', () => {
                    calculateSalary();
                    renderStructureBreakdown();
                });
                structurePreviewTableBody.addEventListener('input', (event) => {
                    const amountInput = event.target.closest('.component-amount-input');

                    if (!amountInput) {
                        return;
                    }

                    const row = amountInput.closest('tr');
                    const checkbox = row?.querySelector('.component-applicable-input');
                    const componentId = Number(amountInput.dataset.componentId);
                    const category = amountInput.dataset.category;

                    syncStructureRowToPrimaryInputs(category, amountInput.value, checkbox ? checkbox.checked : true);

                    // Real-time recalculation of derived components
                    const structureId = structureSelect.value;
                    const structure = structurePayload[structureId];
                    if (structure && category !== 'professional_tax' && category !== 'pf_employee' && category !== 'esi_employee') {
                        const basic = parseFloat(document.getElementById('basic').value) || 0;
                        const hra = parseFloat(document.getElementById('hra').value) || 0;
                        const da = parseFloat(document.getElementById('da').value) || 0;
                        const special = parseFloat(document.getElementById('special_allowance').value) || 0;
                        const conv = parseFloat(document.getElementById('conveyance').value) || 0;
                        const medical = parseFloat(document.getElementById('medical').value) || 0;
                        const other = parseFloat(document.getElementById('other_allowances').value) || 0;
                        const gross = basic + hra + da + special + conv + medical + other;

                        structurePreviewTableBody.querySelectorAll('.component-amount-input').forEach(input => {
                            if (input === amountInput) return; // Don't rewrite what user is typing

                            const compId = Number(input.dataset.componentId);
                            const component = structure.components.find(c => c.id === compId);
                            if (!component) return;

                            const isDerived = ['slab_based', 'percent_of_basic', 'percent_of_gross'].includes(component.calculation_type || '');
                            if (!isDerived) return;

                            const compCheckbox = input.closest('tr')?.querySelector('.component-applicable-input');
                            if (!compCheckbox || !compCheckbox.checked) return;

                            const newAmount = calculateComponentAmount(component, basic, gross);
                            input.value = newAmount.toFixed(2);

                            // Keep overrides fresh without destroying DOM sync state
                            if (!structureComponentOverrides[String(compId)]) structureComponentOverrides[String(compId)] = {};
                            structureComponentOverrides[String(compId)].monthly_amount = newAmount;

                            syncStructureRowToPrimaryInputs(component.category, newAmount, true);
                        });
                    }

                    calculateSalary();
                    captureStructureOverrides();
                });
                structurePreviewTableBody.addEventListener('change', (event) => {
                    const checkbox = event.target.closest('.component-applicable-input');

                    if (!checkbox) {
                        return;
                    }

                    const componentId = String(checkbox.dataset.componentId);
                    captureStructureOverrides();

                    if (checkbox.checked) {
                        // Force recalculation when checked
                        if (!structureComponentOverrides[componentId]) structureComponentOverrides[componentId] = {};
                        structureComponentOverrides[componentId].force_recalc = true;
                    } else {
                        // Set amount to 0 when unchecked
                        if (structureComponentOverrides[componentId]) {
                            structureComponentOverrides[componentId].monthly_amount = 0;
                        }
                    }

                    renderStructureBreakdown(true);

                    // Sync to primary inputs
                    const row = structurePreviewTableBody.querySelector(`input.component-applicable-input[data-component-id="${componentId}"]`).closest('tr');
                    const amountInput = row?.querySelector('.component-amount-input');
                    syncStructureRowToPrimaryInputs(checkbox.dataset.category, amountInput ? amountInput.value : 0, checkbox.checked);
                    calculateSalary();
                });
                structureSelect.addEventListener('change', applyStructure);

                // Initial calculation
                if (structureSelect.value) {
                    applyStructure();
                } else {
                    calculateSalary();
                    renderStructureBreakdown();
                    syncUiMode();
                }
            });
        </script>
    @endpush
@endsection