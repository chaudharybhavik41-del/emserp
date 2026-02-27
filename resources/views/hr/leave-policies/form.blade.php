@extends('layouts.erp')

@section('title', isset($policy) ? 'Edit Leave Policy' : 'Add Leave Policy')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($policy) ? 'Edit Leave Policy' : 'Add Leave Policy' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.leave-policies.index') }}">Leave Policies</a></li>
                <li class="breadcrumb-item active">{{ isset($policy) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <form method="POST" 
          action="{{ isset($policy) ? route('hr.leave-policies.update', $policy) : route('hr.leave-policies.store') }}"
          id="policyForm">
        @csrf
        @if(isset($policy))
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-4">
                {{-- Basic Info --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                   id="code" name="code" 
                                   value="{{ old('code', $policy->code ?? '') }}" 
                                   maxlength="20" required style="text-transform: uppercase;">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" 
                                   value="{{ old('name', $policy->name ?? '') }}" 
                                   maxlength="100" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="2">{{ old('description', $policy->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="applicable_from_months" class="form-label">Applicable After (months)</label>
                            <input type="number" class="form-control @error('applicable_from_months') is-invalid @enderror" 
                                   id="applicable_from_months" name="applicable_from_months" 
                                   value="{{ old('applicable_from_months', $policy->applicable_from_months ?? 0) }}" 
                                   min="0" max="12">
                            @error('applicable_from_months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Leave entitlements start after this many months</small>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" 
                                   name="is_active" value="1"
                                   {{ old('is_active', $policy->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($policy) ? 'Update Policy' : 'Create Policy' }}
                            </button>
                            <a href="{{ route('hr.leave-policies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                {{-- Leave Entitlements --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Leave Entitlements</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addEntitlement">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="entitlements-container">
                            @if(isset($policy) && $policy->entitlements->count())
                                @foreach($policy->entitlements as $index => $entitlement)
                                    <div class="entitlement-row border rounded p-3 mb-2">
                                        <div class="row align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label small">Leave Type</label>
                                                <select class="form-select form-select-sm" name="leave_entitlements[{{ $index }}][leave_type_id]" required>
                                                    <option value="">-- Select --</option>
                                                    @foreach($leaveTypes as $type)
                                                        <option value="{{ $type->id }}" {{ $entitlement->hr_leave_type_id == $type->id ? 'selected' : '' }}>
                                                            {{ $type->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Annual Days</label>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="leave_entitlements[{{ $index }}][annual_entitlement]"
                                                       value="{{ $entitlement->annual_entitlement }}"
                                                       min="0" max="365" step="0.5" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Monthly Accrual</label>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="leave_entitlements[{{ $index }}][monthly_accrual]"
                                                       value="{{ $entitlement->monthly_accrual }}"
                                                       min="0" max="31" step="0.1">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Max Accumulation</label>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="leave_entitlements[{{ $index }}][max_accumulation]"
                                                       value="{{ $entitlement->max_accumulation }}"
                                                       min="0" step="0.5">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-entitlement">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>

                        <div id="no-entitlements" class="text-center text-muted py-4" style="{{ isset($policy) && $policy->entitlements->count() ? 'display: none;' : '' }}">
                            <i class="bi bi-calendar2-event display-6 d-block mb-2"></i>
                            No leave entitlements added. Click "Add" to define leave types.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Template for new entitlement row --}}
<template id="entitlement-template">
    <div class="entitlement-row border rounded p-3 mb-2">
        <div class="row align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Leave Type</label>
                <select class="form-select form-select-sm" name="leave_entitlements[__INDEX__][leave_type_id]" required>
                    <option value="">-- Select --</option>
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Annual Days</label>
                <input type="number" class="form-control form-control-sm" 
                       name="leave_entitlements[__INDEX__][annual_entitlement]"
                       min="0" max="365" step="0.5" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Monthly Accrual</label>
                <input type="number" class="form-control form-control-sm" 
                       name="leave_entitlements[__INDEX__][monthly_accrual]"
                       min="0" max="31" step="0.1">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Max Accumulation</label>
                <input type="number" class="form-control form-control-sm" 
                       name="leave_entitlements[__INDEX__][max_accumulation]"
                       min="0" step="0.5">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-danger remove-entitlement">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let entitlementIndex = {{ isset($policy) ? $policy->entitlements->count() : 0 }};
    const container = document.getElementById('entitlements-container');
    const noEntitlements = document.getElementById('no-entitlements');
    const template = document.getElementById('entitlement-template');
    
    document.getElementById('addEntitlement').addEventListener('click', function() {
        const html = template.innerHTML.replace(/__INDEX__/g, entitlementIndex);
        container.insertAdjacentHTML('beforeend', html);
        entitlementIndex++;
        noEntitlements.style.display = 'none';
    });
    
    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-entitlement')) {
            e.target.closest('.entitlement-row').remove();
            if (container.querySelectorAll('.entitlement-row').length === 0) {
                noEntitlements.style.display = '';
            }
        }
    });
});
</script>
@endpush
@endsection
