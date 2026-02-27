@extends('layouts.erp')

@section('title', 'Leave Policy Details')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ $leavePolicy->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.leave-policies.index') }}">Leave Policies</a></li>
                    <li class="breadcrumb-item active">{{ $leavePolicy->code }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('hr.leave-policies.edit', $leavePolicy) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <a href="{{ route('hr.leave-policies.index') }}" class="btn btn-outline-secondary btn-sm">
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
                                    <td><code>{{ $leavePolicy->code }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Name:</td>
                                    <td>{{ $leavePolicy->name }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 50%;">Applicable After:</td>
                                    <td>{{ $leavePolicy->applicable_from_months ?? 0 }} months</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status:</td>
                                    <td>{!! $leavePolicy->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    @if($leavePolicy->description)
                        <hr>
                        <p class="text-muted mb-0">{{ $leavePolicy->description }}</p>
                    @endif
                </div>
            </div>

            {{-- Entitlements --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Leave Entitlements</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Leave Type</th>
                                    <th class="text-center">Annual Days</th>
                                    <th class="text-center">Monthly Accrual</th>
                                    <th class="text-center">Max Accumulation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($leavePolicy->entitlements as $entitlement)
                                    <tr>
                                        <td>
                                            @if($entitlement->leaveType->color)
                                                <span class="badge me-1" style="background-color: {{ $entitlement->leaveType->color }};">
                                                    {{ $entitlement->leaveType->code }}
                                                </span>
                                            @endif
                                            {{ $entitlement->leaveType->name }}
                                            @if(!$entitlement->leaveType->is_paid)
                                                <small class="text-muted">(Unpaid)</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary">{{ $entitlement->annual_entitlement }}</span>
                                        </td>
                                        <td class="text-center">
                                            {{ $entitlement->monthly_accrual ?? '-' }}
                                        </td>
                                        <td class="text-center">
                                            {{ $entitlement->max_accumulation ?? 'Unlimited' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No entitlements defined</td>
                                    </tr>
                                @endforelse
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
                        <span class="text-muted">Leave Types:</span>
                        <span class="badge bg-primary fs-6">{{ $leavePolicy->entitlements->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Annual Days:</span>
                        <span class="badge bg-success fs-6">
                            {{ $leavePolicy->entitlements->sum('annual_entitlement') }}
                        </span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Paid Leave Types:</span>
                        <span>{{ $leavePolicy->entitlements->filter(fn($e) => $e->leaveType->is_paid)->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Unpaid Leave Types:</span>
                        <span>{{ $leavePolicy->entitlements->filter(fn($e) => !$e->leaveType->is_paid)->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
