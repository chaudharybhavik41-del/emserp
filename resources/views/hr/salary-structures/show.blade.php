@extends('layouts.erp')

@section('title', 'Salary Structure Details')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ $structure->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.salary-structures.index') }}">Salary Structures</a></li>
                    <li class="breadcrumb-item active">{{ $structure->code }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('hr.salary-structures.edit', $structure) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <a href="{{ route('hr.salary-structures.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            {{-- Basic Info --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Basic Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Code:</td>
                                    <td><code>{{ $structure->code }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Name:</td>
                                    <td>{{ $structure->name }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;">Status:</td>
                                    <td>{!! $structure->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Description:</td>
                                    <td>{{ $structure->description ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Components --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Salary Components</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Component</th>
                                    <th>Type</th>
                                    <th>Calculation</th>
                                    <th class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $earnings = $structure->components->where('type', 'earning');
                                    $deductions = $structure->components->where('type', 'deduction');
                                    $employer = $structure->components->where('type', 'employer_contribution');
                                @endphp

                                @if($earnings->count())
                                    <tr class="table-success">
                                        <td colspan="4" class="fw-bold"><i class="bi bi-plus-circle me-1"></i> Earnings</td>
                                    </tr>
                                    @foreach($earnings as $comp)
                                        <tr>
                                            <td>
                                                {{ $comp->name }}
                                                <code class="text-muted small">({{ $comp->code }})</code>
                                                @if($comp->is_statutory)
                                                    <span class="badge bg-warning text-dark">Statutory</span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-success">Earning</span></td>
                                            <td>{{ ucfirst($comp->pivot->calculation_type) }}</td>
                                            <td class="text-end">
                                                @if($comp->pivot->calculation_type === 'percentage')
                                                    {{ $comp->pivot->percentage }}% of {{ ucfirst($comp->pivot->based_on ?? 'basic') }}
                                                @else
                                                    ₹{{ number_format($comp->pivot->amount ?? 0, 2) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif

                                @if($deductions->count())
                                    <tr class="table-danger">
                                        <td colspan="4" class="fw-bold"><i class="bi bi-dash-circle me-1"></i> Deductions</td>
                                    </tr>
                                    @foreach($deductions as $comp)
                                        <tr>
                                            <td>
                                                {{ $comp->name }}
                                                <code class="text-muted small">({{ $comp->code }})</code>
                                                @if($comp->is_statutory)
                                                    <span class="badge bg-warning text-dark">Statutory</span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-danger">Deduction</span></td>
                                            <td>{{ ucfirst($comp->pivot->calculation_type) }}</td>
                                            <td class="text-end">
                                                @if($comp->pivot->calculation_type === 'percentage')
                                                    {{ $comp->pivot->percentage }}% of {{ ucfirst($comp->pivot->based_on ?? 'basic') }}
                                                @else
                                                    ₹{{ number_format($comp->pivot->amount ?? 0, 2) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif

                                @if($employer->count())
                                    <tr class="table-info">
                                        <td colspan="4" class="fw-bold"><i class="bi bi-building me-1"></i> Employer Contributions</td>
                                    </tr>
                                    @foreach($employer as $comp)
                                        <tr>
                                            <td>
                                                {{ $comp->name }}
                                                <code class="text-muted small">({{ $comp->code }})</code>
                                                @if($comp->is_statutory)
                                                    <span class="badge bg-warning text-dark">Statutory</span>
                                                @endif
                                            </td>
                                            <td><span class="badge bg-info">Employer</span></td>
                                            <td>{{ ucfirst($comp->pivot->calculation_type) }}</td>
                                            <td class="text-end">
                                                @if($comp->pivot->calculation_type === 'percentage')
                                                    {{ $comp->pivot->percentage }}% of {{ ucfirst($comp->pivot->based_on ?? 'basic') }}
                                                @else
                                                    ₹{{ number_format($comp->pivot->amount ?? 0, 2) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Summary --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Summary</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Components:</span>
                        <span class="badge bg-primary fs-6">{{ $structure->components->count() }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-success">Earnings:</span>
                        <span class="badge bg-success">{{ $earnings->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-danger">Deductions:</span>
                        <span class="badge bg-danger">{{ $deductions->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-info">Employer Contributions:</span>
                        <span class="badge bg-info">{{ $employer->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
