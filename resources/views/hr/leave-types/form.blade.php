@extends('layouts.erp')

@section('title', isset($leaveType) ? 'Edit Leave Type' : 'Add Leave Type')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($leaveType) ? 'Edit Leave Type' : 'Add Leave Type' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.leave-types.index') }}">Leave Types</a></li>
                <li class="breadcrumb-item active">{{ isset($leaveType) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <form method="POST" 
          action="{{ isset($leaveType) ? route('hr.leave-types.update', $leaveType) : route('hr.leave-types.store') }}">
        @csrf
        @if(isset($leaveType))
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-8">
                {{-- Basic Info --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $leaveType->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $leaveType->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="short_name" class="form-label">Short Name</label>
                                <input type="text" class="form-control @error('short_name') is-invalid @enderror" 
                                       id="short_name" name="short_name" 
                                       value="{{ old('short_name', $leaveType->short_name ?? '') }}" 
                                       maxlength="10">
                                @error('short_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="color" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror" 
                                       id="color" name="color" 
                                       value="{{ old('color', $leaveType->color ?? '#6c757d') }}">
                                @error('color')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                       id="sort_order" name="sort_order" 
                                       value="{{ old('sort_order', $leaveType->sort_order ?? 0) }}" 
                                       min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="gender_specific" class="form-label">Gender Specific</label>
                                <select class="form-select @error('gender_specific') is-invalid @enderror" id="gender_specific" name="gender_specific">
                                    <option value="">All Genders</option>
                                    <option value="male" {{ old('gender_specific', $leaveType->gender_specific ?? '') === 'male' ? 'selected' : '' }}>Male Only</option>
                                    <option value="female" {{ old('gender_specific', $leaveType->gender_specific ?? '') === 'female' ? 'selected' : '' }}>Female Only</option>
                                </select>
                                @error('gender_specific')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="2" maxlength="500">{{ old('description', $leaveType->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Leave Rules --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Leave Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="min_days_per_request" class="form-label">Min Days / Request</label>
                                <input type="number" class="form-control @error('min_days_per_request') is-invalid @enderror" 
                                       id="min_days_per_request" name="min_days_per_request" 
                                       value="{{ old('min_days_per_request', $leaveType->min_days_per_request ?? 0.5) }}" 
                                       min="0.5" step="0.5">
                                @error('min_days_per_request')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="max_days_per_request" class="form-label">Max Days / Request</label>
                                <input type="number" class="form-control @error('max_days_per_request') is-invalid @enderror" 
                                       id="max_days_per_request" name="max_days_per_request" 
                                       value="{{ old('max_days_per_request', $leaveType->max_days_per_request ?? '') }}" 
                                       min="0.5" step="0.5">
                                @error('max_days_per_request')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="max_consecutive_days" class="form-label">Max Consecutive Days</label>
                                <input type="number" class="form-control @error('max_consecutive_days') is-invalid @enderror" 
                                       id="max_consecutive_days" name="max_consecutive_days" 
                                       value="{{ old('max_consecutive_days', $leaveType->max_consecutive_days ?? '') }}" 
                                       min="1">
                                @error('max_consecutive_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="notice_days_required" class="form-label">Notice Days Required</label>
                                <input type="number" class="form-control @error('notice_days_required') is-invalid @enderror" 
                                       id="notice_days_required" name="notice_days_required" 
                                       value="{{ old('notice_days_required', $leaveType->notice_days_required ?? 0) }}" 
                                       min="0">
                                @error('notice_days_required')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="document_required_after_days" class="form-label">Document After Days</label>
                                <input type="number" class="form-control @error('document_required_after_days') is-invalid @enderror" 
                                       id="document_required_after_days" name="document_required_after_days" 
                                       value="{{ old('document_required_after_days', $leaveType->document_required_after_days ?? '') }}" 
                                       min="1">
                                @error('document_required_after_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Require document if leave > this days</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Carry Forward Rules --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Carry Forward Rules</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="max_carry_forward_days" class="form-label">Max Carry Forward Days</label>
                                <input type="number" class="form-control @error('max_carry_forward_days') is-invalid @enderror" 
                                       id="max_carry_forward_days" name="max_carry_forward_days" 
                                       value="{{ old('max_carry_forward_days', $leaveType->max_carry_forward_days ?? '') }}" 
                                       min="0">
                                @error('max_carry_forward_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="carry_forward_expiry_months" class="form-label">Expiry (Months)</label>
                                <input type="number" class="form-control @error('carry_forward_expiry_months') is-invalid @enderror" 
                                       id="carry_forward_expiry_months" name="carry_forward_expiry_months" 
                                       value="{{ old('carry_forward_expiry_months', $leaveType->carry_forward_expiry_months ?? '') }}" 
                                       min="0">
                                @error('carry_forward_expiry_months')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">0 = Never expires</small>
                            </div>
                            <div class="col-md-4">
                                <label for="max_negative_days" class="form-label">Max Negative Balance</label>
                                <input type="number" class="form-control @error('max_negative_days') is-invalid @enderror" 
                                       id="max_negative_days" name="max_negative_days" 
                                       value="{{ old('max_negative_days', $leaveType->max_negative_days ?? 0) }}" 
                                       min="0">
                                @error('max_negative_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Options --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="is_paid" 
                                   name="is_paid" value="1"
                                   {{ old('is_paid', $leaveType->is_paid ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_paid">Paid Leave</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="is_encashable" 
                                   name="is_encashable" value="1"
                                   {{ old('is_encashable', $leaveType->is_encashable ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_encashable">Encashable</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="is_carry_forward" 
                                   name="is_carry_forward" value="1"
                                   {{ old('is_carry_forward', $leaveType->is_carry_forward ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_carry_forward">Allow Carry Forward</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="allow_half_day" 
                                   name="allow_half_day" value="1"
                                   {{ old('allow_half_day', $leaveType->allow_half_day ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="allow_half_day">Allow Half Day</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="allow_negative_balance" 
                                   name="allow_negative_balance" value="1"
                                   {{ old('allow_negative_balance', $leaveType->allow_negative_balance ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="allow_negative_balance">Allow Negative Balance</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="is_document_required" 
                                   name="is_document_required" value="1"
                                   {{ old('is_document_required', $leaveType->is_document_required ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_document_required">Document Required</label>
                        </div>
                        <hr>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" 
                                   name="is_active" value="1"
                                   {{ old('is_active', $leaveType->is_active ?? true) ? 'checked' : '' }}>
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
                                {{ isset($leaveType) ? 'Update Leave Type' : 'Create Leave Type' }}
                            </button>
                            <a href="{{ route('hr.leave-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
