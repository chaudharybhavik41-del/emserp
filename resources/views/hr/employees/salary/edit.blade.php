@extends('layouts.erp')

@section('title', 'Edit Employee Salary')

@section('content')
    <div class="container-fluid py-3">
        @php
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

        <h4 class="mb-3">Edit Salary - {{ $employee->full_name }}</h4>
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('hr.employees.salary.update', [$employee, $salary]) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Structure</label>
                            <select class="form-select" id="salaryStructure" name="hr_salary_structure_id">
                                <option value="">Select</option>
                                @foreach($structures as $structure)
                                    <option value="{{ $structure->id }}" @selected(old('hr_salary_structure_id', $salary->hr_salary_structure_id) == $structure->id)>{{ $structure->name }}
                                        ({{ $structure->code }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Selecting a structure applies its rules to the salary fields. You can
                                still fine-tune before saving.</small>
                        </div>
                        <div class="col-md-4"><label class="form-label">Effective From</label><input type="date"
                                class="form-control" name="effective_from"
                                value="{{ old('effective_from', $salary->effective_from?->format('Y-m-d')) }}" required></div>
                        <div class="col-md-4" id="basicFieldWrap"><label class="form-label">Basic</label><input type="number"
                                min="0" step="0.01" class="form-control" id="basic" name="basic"
                                value="{{ old('basic', $salary->monthly_basic) }}" required></div>
                        <div class="col-md-4 earnings-field-wrap"><label class="form-label">HRA</label><input type="number"
                                min="0" step="0.01" class="form-control earning-field" id="hra" name="hra"
                                value="{{ old('hra', $salary->hra) }}"></div>
                        <div class="col-md-4 earnings-field-wrap"><label class="form-label">DA</label><input type="number"
                                min="0" step="0.01" class="form-control earning-field" id="da" name="da"
                                value="{{ old('da', $salary->da) }}"></div>
                        <div class="col-md-4 earnings-field-wrap"><label class="form-label">Special Allowance</label><input
                                type="number" min="0" step="0.01" class="form-control earning-field" id="special_allowance"
                                name="special_allowance" value="{{ old('special_allowance', $salary->special_allowance) }}">
                        </div>
                        <div class="col-md-4 earnings-field-wrap"><label class="form-label">Conveyance</label><input
                                type="number" min="0" step="0.01" class="form-control earning-field" id="conveyance"
                                name="conveyance" value="{{ old('conveyance', $salary->conveyance) }}"></div>
                        <div class="col-md-4 earnings-field-wrap"><label class="form-label">Medical</label><input type="number"
                                min="0" step="0.01" class="form-control earning-field" id="medical" name="medical"
                                value="{{ old('medical', $salary->medical) }}"></div>
                        <div class="col-md-4 earnings-field-wrap"><label class="form-label">Other Allowances</label><input
                                type="number" min="0" step="0.01" class="form-control earning-field" id="other_allowances"
                                name="other_allowances" value="{{ old('other_allowances', $salary->other_allowances) }}"></div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="pfApplicable" name="pf_applicable" value="1"
                                    {{ old('pf_applicable', $salary->pf_applicable) ? 'checked' : '' }}>
                                <label class="form-check-label" for="pfApplicable">PF Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="esiApplicable" name="esi_applicable"
                                    value="1" {{ old('esi_applicable', $salary->esi_applicable) ? 'checked' : '' }}>
                                <label class="form-check-label" for="esiApplicable">ESI Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="ptApplicable" name="pt_applicable" value="1"
                                    {{ old('pt_applicable', $salary->pt_applicable) ? 'checked' : '' }}>
                                <label class="form-check-label" for="ptApplicable">Professional Tax</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light border py-2 small d-none" id="structureDerivedNotice">
                                Basic earnings and PF / ESI / PT applicability are being derived from the selected structure
                                rows below.
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label">Revision Reason</label><textarea class="form-control"
                                name="revision_reason" rows="2">{{ old('revision_reason', $salary->remarks) }}</textarea></div>
                    </div>
                    <div class="card mt-4">
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
                <div class="mt-3"><button class="btn btn-primary">Update Salary</button> <a class="btn btn-outline-secondary" href="{{ route('hr.employees.salary.show', $employee) }}">Cancel</a></div>
            </form>
        </div></div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const structureSelect = document.getElementById('salaryStructure');
        const effectiveFromInput = document.querySelector('input[name="effective_from"]');
        const basicFieldWrap = document.getElementById('basicFieldWrap');
        const earningsFieldWraps = document.querySelectorAll('.earnings-field-wrap');
        const structureDerivedNotice = document.getElementById('structureDerivedNotice');
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

        @php
    $overrides = collect(old('structure_components', $salary->components->map(fn($c) => [
        'hr_salary_component_id' => $c->hr_salary_component_id,
        'applicable' => true,
        'monthly_amount' => (float) $c->monthly_amount
    ])->keyBy('hr_salary_component_id')->all()));

    $overridesPayload = $overrides->mapWithKeys(fn($row, $id) => [
        (string) $id => [
            'applicable' => (bool) ($row['applicable'] ?? false),
            'monthly_amount' => (float) ($row['monthly_amount'] ?? 0),
            'force_recalc' => false
        ]
    ]);
        @endphp

        let structureComponentOverrides = @json($overridesPayload);

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
            return effectiveFromInput?.value || '{{ $salary->effective_from?->format('Y-m-d') }}';
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

            return 0;
        }

        function formatNumber(num) {
            return Number(num || 0).toLocaleString('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
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

            basicFieldWrap.classList.toggle('d-none', hasStructure);
            earningsFieldWraps.forEach((element) => element.classList.toggle('d-none', hasStructure));
            structureDerivedNotice.classList.toggle('d-none', !hasStructure);
            pfCheckbox.disabled = hasStructure;
            esiCheckbox.disabled = hasStructure;
            ptCheckbox.disabled = hasStructure;
        }

        function applyStructure() {
            const structureId = structureSelect.value;

            if (!structureId || !structurePayload[structureId]) {
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

                field.value = component ? calculateComponentAmount(component, basic, basic).toFixed(2) : '0.00';
            }

            pfCheckbox.checked = components.some(component => componentMatches(component, 'pf_employee'));
            esiCheckbox.checked = components.some(component => componentMatches(component, 'esi_employee'));
            ptCheckbox.checked = components.some(component => componentMatches(component, 'professional_tax'));
            renderStructureBreakdown();
            syncUiMode();
        }

        structureSelect.addEventListener('change', applyStructure);
        ['basic', 'hra', 'da', 'special_allowance', 'conveyance', 'medical', 'other_allowances'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', renderStructureBreakdown);
        });
        effectiveFromInput?.addEventListener('change', renderStructureBreakdown);
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
        });
        renderStructureBreakdown();
        syncUiMode();
    });
    </script>
    @endpush
@endsection
