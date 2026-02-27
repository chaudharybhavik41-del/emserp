@extends('layouts.erp')

@section('title', 'Employee Salary - ' . $employee->full_name)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Employee Salary</h1>
            <small class="text-muted">{{ $employee->employee_code }} - {{ $employee->full_name }}</small>
        </div>
        <div>
            @can('hr.salary.create')
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
                                <td class="text-end">₹{{ number_format($currentSalary->basic, 0) }}</td>
                            </tr>
                            <tr>
                                <td>House Rent Allowance (HRA)</td>
                                <td class="text-end">₹{{ number_format($currentSalary->hra, 0) }}</td>
                            </tr>
                            <tr>
                                <td>Dearness Allowance (DA)</td>
                                <td class="text-end">₹{{ number_format($currentSalary->da, 0) }}</td>
                            </tr>
                            <tr>
                                <td>Special Allowance</td>
                                <td class="text-end">₹{{ number_format($currentSalary->special_allowance, 0) }}</td>
                            </tr>
                            <tr>
                                <td>Conveyance Allowance</td>
                                <td class="text-end">₹{{ number_format($currentSalary->conveyance, 0) }}</td>
                            </tr>
                            <tr>
                                <td>Medical Allowance</td>
                                <td class="text-end">₹{{ number_format($currentSalary->medical, 0) }}</td>
                            </tr>
                            <tr>
                                <td>Other Allowances</td>
                                <td class="text-end">₹{{ number_format($currentSalary->other_allowances, 0) }}</td>
                            </tr>
                            <tr class="table-success fw-bold">
                                <td>Gross Salary</td>
                                <td class="text-end">₹{{ number_format($currentSalary->gross_salary, 0) }}</td>
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
                                <td class="text-end">₹{{ number_format($currentSalary->pf_employee, 0) }}</td>
                            </tr>
                            <tr>
                                <td>
                                    ESI
                                    @if(!$currentSalary->esi_applicable)
                                        <span class="badge bg-secondary">N/A</span>
                                    @endif
                                </td>
                                <td class="text-end">₹{{ number_format($currentSalary->esi_employee, 0) }}</td>
                            </tr>
                            <tr>
                                <td>
                                    Professional Tax
                                    @if(!$currentSalary->pt_applicable)
                                        <span class="badge bg-secondary">N/A</span>
                                    @endif
                                </td>
                                <td class="text-end">₹{{ number_format($currentSalary->professional_tax, 0) }}</td>
                            </tr>
                            <tr class="table-danger fw-bold">
                                <td>Total Deductions</td>
                                <td class="text-end">₹{{ number_format($currentSalary->total_deductions, 0) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <hr>

                    <h6 class="text-info mb-3"><i class="bi bi-building me-1"></i> Employer Contribution</h6>
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td>PF (Employer)</td>
                                <td class="text-end">₹{{ number_format($currentSalary->pf_employer, 0) }}</td>
                            </tr>
                            <tr>
                                <td>ESI (Employer)</td>
                                <td class="text-end">₹{{ number_format($currentSalary->esi_employer, 0) }}</td>
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
                            <h3 class="mb-0">₹{{ number_format($currentSalary->net_salary, 0) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white text-center">
                        <div class="card-body py-3">
                            <h6 class="card-title mb-1">Gross Salary</h6>
                            <h3 class="mb-0">₹{{ number_format($currentSalary->gross_salary, 0) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white text-center">
                        <div class="card-body py-3">
                            <h6 class="card-title mb-1">CTC (Monthly)</h6>
                            <h3 class="mb-0">₹{{ number_format($currentSalary->ctc, 0) }}</h3>
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
    @else
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        No salary record found for this employee. 
        <a href="{{ route('hr.employees.salary.create', $employee) }}" class="alert-link">Click here to add salary.</a>
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
                            <td class="text-end">₹{{ number_format($salary->basic, 0) }}</td>
                            <td class="text-end">₹{{ number_format($salary->gross_salary, 0) }}</td>
                            <td class="text-end">₹{{ number_format($salary->net_salary, 0) }}</td>
                            <td class="text-end">₹{{ number_format($salary->ctc, 0) }}</td>
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
