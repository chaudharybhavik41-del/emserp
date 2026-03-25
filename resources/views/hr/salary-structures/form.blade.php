@extends('layouts.erp')

@section('title', isset($structure) ? 'Edit Salary Structure' : 'Add Salary Structure')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($structure) ? 'Edit Salary Structure' : 'Add Salary Structure' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.salary-structures.index') }}">Salary Structures</a></li>
                <li class="breadcrumb-item active">{{ isset($structure) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    @include('partials.flash')

    @php
        $totalCount = $totalComponents ?? 0;
        $earningCount = isset($components['earning']) ? $components['earning']->count() : 0;
        $deductionCount = isset($components['deduction']) ? $components['deduction']->count() : 0;
        $employerCount = isset($components['employer_contribution']) ? $components['employer_contribution']->count() : 0;
        $hasComponents = $totalCount > 0;
    @endphp

    @if(!$hasComponents)
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>No Salary Components Found!</h5>
            <p class="mb-2">You need to create salary components before you can create a salary structure.</p>
            <hr>
            <a href="{{ route('hr.salary-components.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> Create Salary Components First
            </a>
            <a href="{{ route('hr.salary-structures.index') }}" class="btn btn-outline-secondary ms-2">Go Back</a>
        </div>
    @else
        <form method="POST" 
              action="{{ isset($structure) ? route('hr.salary-structures.update', $structure) : route('hr.salary-structures.store') }}"
              id="structureForm">
            @csrf
            @if(isset($structure))
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $structure->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $structure->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $structure->description ?? '') }}</textarea>
                            </div>

                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                       {{ old('is_active', $structure->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Available Components</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-success"><i class="bi bi-plus-circle me-1"></i> Earnings</span>
                                <span class="badge bg-success">{{ $earningCount }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-danger"><i class="bi bi-dash-circle me-1"></i> Deductions</span>
                                <span class="badge bg-danger">{{ $deductionCount }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-info"><i class="bi bi-building me-1"></i> Employer</span>
                                <span class="badge bg-info">{{ $employerCount }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i> 
                                    {{ isset($structure) ? 'Update Structure' : 'Create Structure' }}
                                </button>
                                <a href="{{ route('hr.salary-structures.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Select Salary Components <span class="text-danger">*</span></h6>
                            <span class="badge bg-primary" id="componentCount">0 selected</span>
                        </div>
                        <div class="card-body">
                            @error('components')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            @if($earningCount > 0)
                                <h6 class="text-success mb-2"><i class="bi bi-plus-circle me-1"></i> Earnings</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 30px;"></th>
                                                <th>Component</th>
                                                <th style="width: 150px;">Calculation</th>
                                                <th style="width: 120px;">Value</th>
                                                <th style="width: 180px;">Formula / Limits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($components['earning'] as $comp)
                                                @php
                                                    $existingComp = isset($structure) ? $structure->components->firstWhere('id', $comp->id) : null;
                                                    $isSelected = $existingComp ? true : false;
                                                    $calcType = $existingComp->pivot->calculation_type ?? $comp->calculation_type ?? 'fixed';
                                                    $calcValue = $existingComp->pivot->value ?? $comp->default_value ?? '';
                                                    $percentage = $existingComp->pivot->percentage ?? $comp->percentage ?? '';
                                                    $formula = $existingComp->pivot->formula ?? '';
                                                    $minValue = $existingComp->pivot->min_value ?? '';
                                                    $maxValue = $existingComp->pivot->max_value ?? '';
                                                    $isMandatory = (bool) ($existingComp->pivot->is_mandatory ?? false);
                                                    $sortOrder = $existingComp->pivot->sort_order ?? $loop->iteration;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" class="form-check-input component-checkbox" 
                                                               data-component-id="{{ $comp->id }}" {{ $isSelected ? 'checked' : '' }}>
                                                    </td>
                                                    <td>
                                                        {{ $comp->name }} <code class="text-muted">({{ $comp->code }})</code>
                                                        @if($comp->is_statutory)<span class="badge bg-warning text-dark">Statutory</span>@endif
                                                    </td>
                                                    <td>
                                                        <select class="form-select form-select-sm component-calc" data-component-id="{{ $comp->id }}" {{ $isSelected ? '' : 'disabled' }}>
                                                            <option value="fixed" {{ $calcType == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                                            <option value="percent_of_basic" {{ $calcType == 'percent_of_basic' ? 'selected' : '' }}>% of Basic</option>
                                                            <option value="percent_of_gross" {{ $calcType == 'percent_of_gross' ? 'selected' : '' }}>% of Gross</option>
                                                            <option value="percent_of_ctc" {{ $calcType == 'percent_of_ctc' ? 'selected' : '' }}>% of CTC</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm component-value" 
                                                               data-component-id="{{ $comp->id }}"
                                                               value="{{ str_contains($calcType, 'percent') ? $percentage : $calcValue }}"
                                                               step="0.01" min="0" {{ $isSelected ? '' : 'disabled' }}>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm component-formula mb-1"
                                                               data-component-id="{{ $comp->id }}"
                                                               value="{{ $formula }}"
                                                               placeholder="Formula"
                                                               {{ $isSelected ? '' : 'disabled' }}>
                                                        <div class="d-flex gap-1 mb-1">
                                                            <input type="number" class="form-control form-control-sm component-min"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $minValue }}"
                                                                   step="0.01" min="0" placeholder="Min"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                            <input type="number" class="form-control form-control-sm component-max"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $maxValue }}"
                                                                   step="0.01" min="0" placeholder="Max"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input component-mandatory"
                                                                       data-component-id="{{ $comp->id }}"
                                                                       {{ $isMandatory ? 'checked' : '' }}
                                                                       {{ $isSelected ? '' : 'disabled' }}>
                                                                <label class="form-check-label small">Mandatory</label>
                                                            </div>
                                                            <input type="number" class="form-control form-control-sm component-sort-order"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $sortOrder }}"
                                                                   min="0" style="width: 72px;"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if($deductionCount > 0)
                                <h6 class="text-danger mb-2"><i class="bi bi-dash-circle me-1"></i> Deductions</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 30px;"></th>
                                                <th>Component</th>
                                                <th style="width: 150px;">Calculation</th>
                                                <th style="width: 120px;">Value</th>
                                                <th style="width: 180px;">Formula / Limits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($components['deduction'] as $comp)
                                                @php
                                                    $existingComp = isset($structure) ? $structure->components->firstWhere('id', $comp->id) : null;
                                                    $isSelected = $existingComp ? true : false;
                                                    $calcType = $existingComp->pivot->calculation_type ?? $comp->calculation_type ?? 'fixed';
                                                    $calcValue = $existingComp->pivot->value ?? $comp->default_value ?? '';
                                                    $percentage = $existingComp->pivot->percentage ?? $comp->percentage ?? '';
                                                    $formula = $existingComp->pivot->formula ?? '';
                                                    $minValue = $existingComp->pivot->min_value ?? '';
                                                    $maxValue = $existingComp->pivot->max_value ?? '';
                                                    $isMandatory = (bool) ($existingComp->pivot->is_mandatory ?? false);
                                                    $sortOrder = $existingComp->pivot->sort_order ?? $loop->iteration;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" class="form-check-input component-checkbox" 
                                                               data-component-id="{{ $comp->id }}" {{ $isSelected ? 'checked' : '' }}>
                                                    </td>
                                                    <td>
                                                        {{ $comp->name }} <code class="text-muted">({{ $comp->code }})</code>
                                                        @if($comp->is_statutory)<span class="badge bg-warning text-dark">Statutory</span>@endif
                                                    </td>
                                                    <td>
                                                        <select class="form-select form-select-sm component-calc" data-component-id="{{ $comp->id }}" {{ $isSelected ? '' : 'disabled' }}>
                                                            <option value="fixed" {{ $calcType == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                                            <option value="percent_of_basic" {{ $calcType == 'percent_of_basic' ? 'selected' : '' }}>% of Basic</option>
                                                            <option value="percent_of_gross" {{ $calcType == 'percent_of_gross' ? 'selected' : '' }}>% of Gross</option>
                                                            <option value="slab_based" {{ $calcType == 'slab_based' ? 'selected' : '' }}>Slab Based</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm component-value" 
                                                               data-component-id="{{ $comp->id }}"
                                                               value="{{ str_contains($calcType, 'percent') ? $percentage : $calcValue }}"
                                                               step="0.01" min="0" {{ $isSelected ? '' : 'disabled' }}>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm component-formula mb-1"
                                                               data-component-id="{{ $comp->id }}"
                                                               value="{{ $formula }}"
                                                               placeholder="Formula"
                                                               {{ $isSelected ? '' : 'disabled' }}>
                                                        <div class="d-flex gap-1 mb-1">
                                                            <input type="number" class="form-control form-control-sm component-min"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $minValue }}"
                                                                   step="0.01" min="0" placeholder="Min"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                            <input type="number" class="form-control form-control-sm component-max"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $maxValue }}"
                                                                   step="0.01" min="0" placeholder="Max"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input component-mandatory"
                                                                       data-component-id="{{ $comp->id }}"
                                                                       {{ $isMandatory ? 'checked' : '' }}
                                                                       {{ $isSelected ? '' : 'disabled' }}>
                                                                <label class="form-check-label small">Mandatory</label>
                                                            </div>
                                                            <input type="number" class="form-control form-control-sm component-sort-order"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $sortOrder }}"
                                                                   min="0" style="width: 72px;"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if($employerCount > 0)
                                <h6 class="text-info mb-2"><i class="bi bi-building me-1"></i> Employer Contributions</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 30px;"></th>
                                                <th>Component</th>
                                                <th style="width: 150px;">Calculation</th>
                                                <th style="width: 120px;">Value</th>
                                                <th style="width: 180px;">Formula / Limits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($components['employer_contribution'] as $comp)
                                                @php
                                                    $existingComp = isset($structure) ? $structure->components->firstWhere('id', $comp->id) : null;
                                                    $isSelected = $existingComp ? true : false;
                                                    $calcType = $existingComp->pivot->calculation_type ?? $comp->calculation_type ?? 'fixed';
                                                    $calcValue = $existingComp->pivot->value ?? $comp->default_value ?? '';
                                                    $percentage = $existingComp->pivot->percentage ?? $comp->percentage ?? '';
                                                    $formula = $existingComp->pivot->formula ?? '';
                                                    $minValue = $existingComp->pivot->min_value ?? '';
                                                    $maxValue = $existingComp->pivot->max_value ?? '';
                                                    $isMandatory = (bool) ($existingComp->pivot->is_mandatory ?? false);
                                                    $sortOrder = $existingComp->pivot->sort_order ?? $loop->iteration;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" class="form-check-input component-checkbox" 
                                                               data-component-id="{{ $comp->id }}" {{ $isSelected ? 'checked' : '' }}>
                                                    </td>
                                                    <td>
                                                        {{ $comp->name }} <code class="text-muted">({{ $comp->code }})</code>
                                                        @if($comp->is_statutory)<span class="badge bg-warning text-dark">Statutory</span>@endif
                                                    </td>
                                                    <td>
                                                        <select class="form-select form-select-sm component-calc" data-component-id="{{ $comp->id }}" {{ $isSelected ? '' : 'disabled' }}>
                                                            <option value="fixed" {{ $calcType == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                                            <option value="percent_of_basic" {{ $calcType == 'percent_of_basic' ? 'selected' : '' }}>% of Basic</option>
                                                            <option value="percent_of_gross" {{ $calcType == 'percent_of_gross' ? 'selected' : '' }}>% of Gross</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm component-value" 
                                                               data-component-id="{{ $comp->id }}"
                                                               value="{{ str_contains($calcType, 'percent') ? $percentage : $calcValue }}"
                                                               step="0.01" min="0" {{ $isSelected ? '' : 'disabled' }}>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm component-formula mb-1"
                                                               data-component-id="{{ $comp->id }}"
                                                               value="{{ $formula }}"
                                                               placeholder="Formula"
                                                               {{ $isSelected ? '' : 'disabled' }}>
                                                        <div class="d-flex gap-1 mb-1">
                                                            <input type="number" class="form-control form-control-sm component-min"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $minValue }}"
                                                                   step="0.01" min="0" placeholder="Min"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                            <input type="number" class="form-control form-control-sm component-max"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $maxValue }}"
                                                                   step="0.01" min="0" placeholder="Max"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="form-check">
                                                                <input type="checkbox" class="form-check-input component-mandatory"
                                                                       data-component-id="{{ $comp->id }}"
                                                                       {{ $isMandatory ? 'checked' : '' }}
                                                                       {{ $isSelected ? '' : 'disabled' }}>
                                                                <label class="form-check-label small">Mandatory</label>
                                                            </div>
                                                            <input type="number" class="form-control form-control-sm component-sort-order"
                                                                   data-component-id="{{ $comp->id }}"
                                                                   value="{{ $sortOrder }}"
                                                                   min="0" style="width: 72px;"
                                                                   {{ $isSelected ? '' : 'disabled' }}>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div id="componentInputs"></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>

@if($hasComponents)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('structureForm');
    const componentInputs = document.getElementById('componentInputs');
    
    function updateComponentCount() {
        const count = document.querySelectorAll('.component-checkbox:checked').length;
        document.getElementById('componentCount').textContent = count + ' selected';
    }
    
    function toggleComponentFields(componentId, enabled) {
        const calc = document.querySelector(`.component-calc[data-component-id="${componentId}"]`);
        const value = document.querySelector(`.component-value[data-component-id="${componentId}"]`);
        const formula = document.querySelector(`.component-formula[data-component-id="${componentId}"]`);
        const min = document.querySelector(`.component-min[data-component-id="${componentId}"]`);
        const max = document.querySelector(`.component-max[data-component-id="${componentId}"]`);
        const mandatory = document.querySelector(`.component-mandatory[data-component-id="${componentId}"]`);
        const sortOrder = document.querySelector(`.component-sort-order[data-component-id="${componentId}"]`);
        if (calc) calc.disabled = !enabled;
        if (value) value.disabled = !enabled;
        if (formula) formula.disabled = !enabled;
        if (min) min.disabled = !enabled;
        if (max) max.disabled = !enabled;
        if (mandatory) mandatory.disabled = !enabled;
        if (sortOrder) sortOrder.disabled = !enabled;
    }
    
    function generateHiddenInputs() {
        componentInputs.innerHTML = '';
        let index = 0;
        
        document.querySelectorAll('.component-checkbox:checked').forEach(checkbox => {
            const componentId = checkbox.dataset.componentId;
            const calc = document.querySelector(`.component-calc[data-component-id="${componentId}"]`);
            const value = document.querySelector(`.component-value[data-component-id="${componentId}"]`);
            const formula = document.querySelector(`.component-formula[data-component-id="${componentId}"]`);
            const min = document.querySelector(`.component-min[data-component-id="${componentId}"]`);
            const max = document.querySelector(`.component-max[data-component-id="${componentId}"]`);
            const mandatory = document.querySelector(`.component-mandatory[data-component-id="${componentId}"]`);
            const sortOrder = document.querySelector(`.component-sort-order[data-component-id="${componentId}"]`);
            const calcType = calc ? calc.value : 'fixed';
            const calcValue = value ? value.value : 0;
            const formulaValue = formula ? formula.value : '';
            const minValue = min ? min.value : '';
            const maxValue = max ? max.value : '';
            const sortOrderValue = sortOrder ? sortOrder.value : (index + 1);
            
            componentInputs.innerHTML += `
                <input type="hidden" name="components[${index}][id]" value="${componentId}">
                <input type="hidden" name="components[${index}][calculation_type]" value="${calcType}">
                <input type="hidden" name="components[${index}][calculation_value]" value="${calcType === 'fixed' ? calcValue : 0}">
                <input type="hidden" name="components[${index}][percentage]" value="${calcType.includes('percent') ? calcValue : ''}">
                <input type="hidden" name="components[${index}][formula]" value="${calcType === 'formula' ? formulaValue : formulaValue}">
                <input type="hidden" name="components[${index}][min_value]" value="${minValue}">
                <input type="hidden" name="components[${index}][max_value]" value="${maxValue}">
                <input type="hidden" name="components[${index}][is_mandatory]" value="${mandatory && mandatory.checked ? 1 : 0}">
                <input type="hidden" name="components[${index}][sort_order]" value="${sortOrderValue}">
            `;
            index++;
        });
    }
    
    document.querySelectorAll('.component-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleComponentFields(this.dataset.componentId, this.checked);
            updateComponentCount();
        });
    });
    
    form.addEventListener('submit', function(e) {
        generateHiddenInputs();
        if (document.querySelectorAll('.component-checkbox:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one salary component.');
        }
    });
    
    updateComponentCount();

    // Auto calculate Professional Tax (PT) when Basic Salary changes
    const ptSlabs = {!! json_encode(\App\Models\Hr\HrProfessionalTaxSlab::where('is_active', true)->orderBy('salary_from')->get()->map(function($slab) {
        return [
            'salary_from' => (float) $slab->salary_from,
            'salary_to' => (float) $slab->salary_to,
            'tax_amount' => (float) $slab->tax_amount
        ];
    })->toArray()) !!};

    function calculatePT(basicSalary) {
        if (!basicSalary || isNaN(basicSalary)) return 0;
        basicSalary = parseFloat(basicSalary);
        let tax = 0;
        ptSlabs.forEach(s => {
            if (basicSalary >= s.salary_from && (s.salary_to === null || s.salary_to === 0 || basicSalary <= s.salary_to)) {
                tax = s.tax_amount;
            }
        });
        return tax;
    }

    document.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('component-value')) {
            const tr = e.target.closest('tr');
            if (tr && tr.textContent.includes('BASIC')) {
                const ptTr = Array.from(document.querySelectorAll('tr')).find(row => row.textContent.includes('PT') && row.querySelector('.component-value'));
                if (ptTr) {
                    const ptInput = ptTr.querySelector('.component-value');
                    const ptCalc = ptTr.querySelector('.component-calc');
                    if (ptInput && ptCalc && (ptCalc.value === 'slab_based' || ptCalc.value === 'fixed')) {
                        ptInput.value = parseFloat(calculatePT(e.target.value)).toFixed(2);
                    }
                }
            }
        }
    });
});
</script>
@endpush
@endif
@endsection
