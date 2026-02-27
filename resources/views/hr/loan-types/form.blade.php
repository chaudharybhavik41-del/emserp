@extends('layouts.erp')

@section('title', isset($loanType) ? 'Edit Loan Type' : 'Add Loan Type')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($loanType) ? 'Edit Loan Type' : 'Add Loan Type' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.loan-types.index') }}">Loan Types</a></li>
                <li class="breadcrumb-item active">{{ isset($loanType) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" 
                          action="{{ isset($loanType) ? route('hr.loan-types.update', $loanType) : route('hr.loan-types.store') }}">
                        @csrf
                        @if(isset($loanType))
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $loanType->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $loanType->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="2" maxlength="500">{{ old('description', $loanType->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Loan Amount</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="min_amount" class="form-label">Minimum Amount (₹)</label>
                                <input type="number" class="form-control @error('min_amount') is-invalid @enderror" 
                                       id="min_amount" name="min_amount" 
                                       value="{{ old('min_amount', $loanType->min_amount ?? '') }}" 
                                       min="0" step="1000">
                                @error('min_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="max_amount" class="form-label">Maximum Amount (₹)</label>
                                <input type="number" class="form-control @error('max_amount') is-invalid @enderror" 
                                       id="max_amount" name="max_amount" 
                                       value="{{ old('max_amount', $loanType->max_amount ?? '') }}" 
                                       min="0" step="1000">
                                @error('max_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Tenure</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="min_tenure_months" class="form-label">Min Tenure (Months)</label>
                                <input type="number" class="form-control @error('min_tenure_months') is-invalid @enderror" 
                                       id="min_tenure_months" name="min_tenure_months" 
                                       value="{{ old('min_tenure_months', $loanType->min_tenure_months ?? 1) }}" 
                                       min="1" max="120">
                                @error('min_tenure_months')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="max_tenure_months" class="form-label">Max Tenure (Months)</label>
                                <input type="number" class="form-control @error('max_tenure_months') is-invalid @enderror" 
                                       id="max_tenure_months" name="max_tenure_months" 
                                       value="{{ old('max_tenure_months', $loanType->max_tenure_months ?? 12) }}" 
                                       min="1" max="120">
                                @error('max_tenure_months')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Interest & Fees</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="interest_type" class="form-label">Interest Type</label>
                                <select class="form-select @error('interest_type') is-invalid @enderror" id="interest_type" name="interest_type">
                                    <option value="none" {{ old('interest_type', $loanType->interest_type ?? '') === 'none' ? 'selected' : '' }}>Interest Free</option>
                                    <option value="simple" {{ old('interest_type', $loanType->interest_type ?? 'simple') === 'simple' ? 'selected' : '' }}>Simple Interest</option>
                                    <option value="compound" {{ old('interest_type', $loanType->interest_type ?? '') === 'compound' ? 'selected' : '' }}>Compound Interest</option>
                                </select>
                                @error('interest_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="interest_rate" class="form-label">Interest Rate (%)</label>
                                <input type="number" class="form-control @error('interest_rate') is-invalid @enderror" 
                                       id="interest_rate" name="interest_rate" 
                                       value="{{ old('interest_rate', $loanType->interest_rate ?? 0) }}" 
                                       min="0" max="50" step="0.01">
                                @error('interest_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="processing_fee_percentage" class="form-label">Processing Fee (%)</label>
                                <input type="number" class="form-control @error('processing_fee_percentage') is-invalid @enderror" 
                                       id="processing_fee_percentage" name="processing_fee_percentage" 
                                       value="{{ old('processing_fee_percentage', $loanType->processing_fee_percentage ?? 0) }}" 
                                       min="0" max="10" step="0.01">
                                @error('processing_fee_percentage')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Eligibility & Rules</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="min_service_months" class="form-label">Min Service (Months)</label>
                                <input type="number" class="form-control @error('min_service_months') is-invalid @enderror" 
                                       id="min_service_months" name="min_service_months" 
                                       value="{{ old('min_service_months', $loanType->min_service_months ?? 6) }}" 
                                       min="0">
                                @error('min_service_months')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Employee must complete this service period</small>
                            </div>
                            <div class="col-md-4">
                                <label for="max_emi_percentage" class="form-label">Max EMI % of Salary</label>
                                <input type="number" class="form-control @error('max_emi_percentage') is-invalid @enderror" 
                                       id="max_emi_percentage" name="max_emi_percentage" 
                                       value="{{ old('max_emi_percentage', $loanType->max_emi_percentage ?? 50) }}" 
                                       min="0" max="100">
                                @error('max_emi_percentage')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="requires_guarantor" 
                                           name="requires_guarantor" value="1"
                                           {{ old('requires_guarantor', $loanType->requires_guarantor ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requires_guarantor">Requires Guarantor</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="requires_approval" 
                                           name="requires_approval" value="1"
                                           {{ old('requires_approval', $loanType->requires_approval ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requires_approval">Requires Approval</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" 
                                           name="is_active" value="1"
                                           {{ old('is_active', $loanType->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($loanType) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('hr.loan-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Common Loan Types</h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li><strong>Salary Advance:</strong> Short-term, interest-free</li>
                        <li><strong>Personal Loan:</strong> Medium-term with interest</li>
                        <li><strong>Emergency Loan:</strong> Quick disbursement</li>
                        <li><strong>Festival Advance:</strong> Annual, interest-free</li>
                        <li><strong>Vehicle Loan:</strong> Long-term with interest</li>
                        <li><strong>Housing Loan:</strong> Long-term with low interest</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('interest_type').addEventListener('change', function() {
    document.getElementById('interest_rate').disabled = this.value === 'none';
    if (this.value === 'none') {
        document.getElementById('interest_rate').value = 0;
    }
});
</script>
@endpush
@endsection
