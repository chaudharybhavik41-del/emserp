@extends('layouts.erp')

@section('title', 'Employee Salary - ' . $employee->full_name)

@section('content')
    <div class="container-fluid">
        @include('hr.employees.partials.hub-nav', ['employee' => $employee, 'activeSection' => 'salary'])

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h4 mb-0">Employee Salary</h1>
                <small class="text-muted">{{ $employee->employee_code }} - {{ $employee->full_name }}</small>
            </div>
            <div>
                @can('hr.employee.update')
                    @if($currentSalary)
                        <a href="{{ route('hr.employees.salary.edit', [$employee, $currentSalary]) }}" class="btn btn-success">
                            <i class="bi bi-pencil me-1"></i> Edit Current Salary
                        </a>
                    @endif
                    <a href="{{ route('hr.employees.salary.create', $employee) }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Add/Revise Salary
                    </a>
                @endcan
                <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Profile
                </a>
            </div>
        </div>

        @if($currentSalary)
            @php

            
                $salaryStructure = $currentSalary->salaryStructure;
                
                $structureComponents = $salaryStructure?->components
                    ? $salaryStructure->components->sortBy(fn ($component) => $component->pivot->sort_order ?? $component->sort_order ?? 999)
                    : collect();
                $displayGross = (float) $currentSalary->gross_salary;
                $displayDeductions = (float) $currentSalary->total_deductions;
                $displayNet = $displayGross - $displayDeductions;
                $displayMonthlyCtc = $displayGross + (float) $currentSalary->pf_employer + (float) $currentSalary->esi_employer + (float) $currentSalary->lwf_employer;
                $displayAnnualCtc = $displayMonthlyCtc * 12;
            @endphp

            {{-- Current Salary Card --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Current Salary (Effective: {{ $currentSalary->effective_from->format('d M Y') }})</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        {{-- Earnings --}}
                        <div class="col-md-6">
                            <h6 class="text-success mb-3"><i class="bi bi-plus-circle me-1"></i> Earnings</h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->basic, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>House Rent Allowance (HRA)</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->hra, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Dearness Allowance (DA)</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->da, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Special Allowance</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->special_allowance, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Conveyance Allowance</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->conveyance, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Medical Allowance</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->medical, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Other Allowances</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->other_allowances, 2) }}</td>
                                    </tr>
                                    <tr class="table-success fw-bold">
                                        <td>Gross Salary</td>
                                        <td class="text-end">₹{{ number_format($displayGross, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Deductions --}}
                        <div class="col-md-6">
                            <h6 class="text-danger mb-3"><i class="bi bi-dash-circle me-1"></i> Deductions</h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>
                                            Provident Fund (PF)
                                            @if(!$currentSalary->pf_applicable)
                                                <span class="badge bg-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₹{{ number_format($currentSalary->pf_employee, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            ESI
                                            @if(!$currentSalary->esi_applicable)
                                                <span class="badge bg-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₹{{ number_format($currentSalary->esi_employee, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            Professional Tax
                                            @if(!$currentSalary->pt_applicable)
                                                <span class="badge bg-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₹{{ number_format($currentSalary->professional_tax, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            TDS
                                            @if(!$employee->tds_applicable)
                                                <span class="badge bg-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₹{{ number_format($currentSalary->tds, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            LWF
                                            @if(!$employee->lwf_applicable)
                                                <span class="badge bg-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-end">₹{{ number_format($currentSalary->lwf_employee, 2) }}</td>
                                    </tr>
                                    <tr class="table-danger fw-bold">
                                        <td>Total Deductions</td>
                                        <td class="text-end">₹{{ number_format($displayDeductions, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <hr>

                            <h6 class="text-info mb-3"><i class="bi bi-building me-1"></i> Employer Contribution</h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>PF (Employer)</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->pf_employer, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>ESI (Employer)</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->esi_employer, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>LWF (Employer)</td>
                                        <td class="text-end">₹{{ number_format($currentSalary->lwf_employer, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Summary --}}
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card bg-success text-white text-center">
                                <div class="card-body py-3">
                                    <h6 class="card-title mb-1">Net Salary</h6>
                                    <h3 class="mb-0">₹{{ number_format($displayNet, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-primary text-white text-center">
                                <div class="card-body py-3">
                                    <h6 class="card-title mb-1">Gross Salary</h6>
                                    <h3 class="mb-0">₹{{ number_format($displayGross, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white text-center">
                                <div class="card-body py-3">
                                    <h6 class="card-title mb-1">CTC (Monthly)</h6>
                                    <h3 class="mb-0">₹{{ number_format($displayMonthlyCtc, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4 offset-md-8">
                            <div class="card border-info text-center">
                                <div class="card-body py-3">
                                    <h6 class="card-title mb-1">CTC (Annual)</h6>
                                    <h3 class="mb-0">₹{{ number_format($displayAnnualCtc, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($currentSalary->revision_reason)
                        <div class="mt-3">
                            <strong>Revision Reason:</strong> {{ $currentSalary->revision_reason }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Salary Structure</h6>
                    @if($salaryStructure)
                        <span class="badge bg-primary">{{ $structureComponents->count() }} components</span>
                    @else
                        <span class="badge bg-secondary">No structure linked</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($salaryStructure)
                        @php
                            $earnings = $structureComponents->where('component_type', 'earning');
                            $deductions = $structureComponents->where('component_type', 'deduction');
                            $employerContributions = $structureComponents->where('component_type', 'employer_contribution');
                        @endphp

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <small class="text-muted d-block">Structure</small>
                                <div class="fw-semibold">{{ $salaryStructure->name }}</div>
                                <div class="small text-muted">{{ $salaryStructure->code }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Base Type</small>
                                <div class="fw-semibold">{{ $salaryStructure->base_type_label }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Frequency</small>
                                <div class="fw-semibold">{{ $salaryStructure->frequency_label }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Status</small>
                                <div class="fw-semibold">
                                    <span class="badge bg-{{ $salaryStructure->is_active ? 'success' : 'secondary' }}">
                                        {{ $salaryStructure->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Payment Day</small>
                                <div class="fw-semibold">{{ $salaryStructure->payment_day ?: '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Description</small>
                                <div class="fw-semibold">{{ $salaryStructure->description ?: '-' }}</div>
                            </div>
                        </div>

                        @if($structureComponents->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Component</th>
                                            <th>Type</th>
                                            <th>Rule</th>
                                            <th>Limits / Formula</th>
                                            <th class="text-center">Mandatory</th>
                                            <th class="text-end">Actual Amount</th>
                                            <th class="text-end">Configured Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach([
                                            ['label' => 'Earnings', 'tone' => 'success', 'rows' => $earnings],
                                            ['label' => 'Deductions', 'tone' => 'danger', 'rows' => $deductions],
                                            ['label' => 'Employer Contributions', 'tone' => 'info', 'rows' => $employerContributions],
                                        ] as $group)
                                            @if($group['rows']->isNotEmpty())
                                                <tr class="table-{{ $group['tone'] }}">
                                                    <td colspan="7" class="fw-bold">{{ $group['label'] }}</td>
                                                </tr>
                                                @foreach($group['rows'] as $component)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold">{{ $component->name }}</div>
                                                            <div class="small text-muted">{{ $component->code }}</div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-{{ $component->component_type === 'earning' ? 'success' : ($component->component_type === 'deduction' ? 'danger' : 'info') }}">
                                                                {{ ucfirst(str_replace('_', ' ', $component->component_type)) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @php($calcType = (string) ($component->pivot->calculation_type ?? 'fixed'))
                                                            @if(str_starts_with($calcType, 'percent_'))
                                                                {{ number_format((float) ($component->pivot->percentage ?? 0), 2) }}% of {{ ucfirst(str_replace('_', ' ', str_replace('percent_of_', '', $calcType))) }}
                                                            @else
                                                                {{ ucfirst(str_replace('_', ' ', $calcType)) }}
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($calcType === 'formula')
                                                                <code>{{ $component->pivot->formula ?: '-' }}</code>
                                                            @elseif(($component->pivot->min_value ?? null) !== null || ($component->pivot->max_value ?? null) !== null)
                                                                Min: {{ $component->pivot->min_value !== null ? number_format((float) $component->pivot->min_value, 2) : '-' }}
                                                                <br>
                                                                Max: {{ $component->pivot->max_value !== null ? number_format((float) $component->pivot->max_value, 2) : '-' }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if($component->pivot->is_mandatory ?? false)
                                                                <span class="badge bg-dark">Yes</span>
                                                            @else
                                                                <span class="text-muted">No</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">₹{{ number_format((float) $currentSalary->effectiveAmountForStructureComponent($component), 2) }}</td>
                                                        <td class="text-end">
                                                            @if(str_starts_with($calcType, 'percent_'))
                                                                {{ number_format((float) ($component->pivot->percentage ?? 0), 2) }}%
                                                            @elseif($calcType === 'formula')
                                                                <code>{{ $component->pivot->formula ?: '-' }}</code>
                                                            @else
                                                                ₹{{ number_format((float) ($component->pivot->value ?? 0), 2) }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-light border mb-0">This salary structure is linked, but no structure components are configured yet.</div>
                        @endif

                        <div class="row g-3 mt-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Earnings</div>
                                    <div class="h5 mb-0">{{ $earnings->count() }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Deductions</div>
                                    <div class="h5 mb-0">{{ $deductions->count() }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Employer Contributions</div>
                                    <div class="h5 mb-0">{{ $employerContributions->count() }}</div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-light border mb-0">No salary structure is linked to this salary record.</div>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                No salary record found for this employee.
                @can('hr.employee.update')
                    <a href="{{ route('hr.employees.salary.create', $employee) }}" class="alert-link">Click here to add salary.</a>
                @endcan
            </div>
        @endif

        {{-- Salary History --}}
        @if($salaryHistory->count() > 1)
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Salary History</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Effective From</th>
                                    <th>Effective To</th>
                                    <th class="text-end">Basic</th>
                                    <th class="text-end">Gross</th>
                                    <th class="text-end">Net</th>
                                    <th class="text-end">CTC</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salaryHistory as $salary)
                                    <tr class="{{ $salary->is_current ? 'table-primary' : '' }}">
                                        <td>
                                            {{ $salary->effective_from->format('d M Y') }}
                                            @if($salary->is_current)
                                                <span class="badge bg-success">Current</span>
                                            @endif
                                        </td>
                                        <td>{{ $salary->effective_to ? $salary->effective_to->format('d M Y') : '-' }}</td>
                                        <td class="text-end">₹{{ number_format($salary->basic, 2) }}</td>
                                        <td class="text-end">₹{{ number_format($salary->gross_salary, 2) }}</td>
                                        <td class="text-end">₹{{ number_format($salary->gross_salary - $salary->total_deductions, 2) }}</td>
                                        <td class="text-end">₹{{ number_format($salary->gross_salary + $salary->pf_employer + $salary->esi_employer, 2) }}</td>
                                        <td>{{ Str::limit($salary->revision_reason, 30) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
