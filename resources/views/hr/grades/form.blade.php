@extends('layouts.erp')

@section('title', isset($grade) ? 'Edit Grade' : 'Add Grade')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($grade) ? 'Edit Grade' : 'Add Grade' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.grades.index') }}">Grades</a></li>
                <li class="breadcrumb-item active">{{ isset($grade) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" 
                          action="{{ isset($grade) ? route('hr.grades.update', $grade) : route('hr.grades.store') }}">
                        @csrf
                        @if(isset($grade))
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('level') is-invalid @enderror" 
                                       id="level" name="level" 
                                       value="{{ old('level', $grade->level ?? 1) }}" 
                                       min="1" max="99" required>
                                @error('level')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $grade->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $grade->name ?? '') }}" 
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
                                      rows="2" maxlength="500">{{ old('description', $grade->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Salary Range - Basic</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="min_basic" class="form-label">Minimum Basic (₹)</label>
                                <input type="number" class="form-control @error('min_basic') is-invalid @enderror" 
                                       id="min_basic" name="min_basic" 
                                       value="{{ old('min_basic', $grade->min_basic ?? '') }}" 
                                       min="0" step="0.01">
                                @error('min_basic')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="max_basic" class="form-label">Maximum Basic (₹)</label>
                                <input type="number" class="form-control @error('max_basic') is-invalid @enderror" 
                                       id="max_basic" name="max_basic" 
                                       value="{{ old('max_basic', $grade->max_basic ?? '') }}" 
                                       min="0" step="0.01">
                                @error('max_basic')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Salary Range - Gross</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="min_gross" class="form-label">Minimum Gross (₹)</label>
                                <input type="number" class="form-control @error('min_gross') is-invalid @enderror" 
                                       id="min_gross" name="min_gross" 
                                       value="{{ old('min_gross', $grade->min_gross ?? '') }}" 
                                       min="0" step="0.01">
                                @error('min_gross')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="max_gross" class="form-label">Maximum Gross (₹)</label>
                                <input type="number" class="form-control @error('max_gross') is-invalid @enderror" 
                                       id="max_gross" name="max_gross" 
                                       value="{{ old('max_gross', $grade->max_gross ?? '') }}" 
                                       min="0" step="0.01">
                                @error('max_gross')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Employment Terms</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="probation_months" class="form-label">Probation Period (Months)</label>
                                <input type="number" class="form-control @error('probation_months') is-invalid @enderror" 
                                       id="probation_months" name="probation_months" 
                                       value="{{ old('probation_months', $grade->probation_months ?? 6) }}" 
                                       min="0" max="24">
                                @error('probation_months')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="notice_period_days" class="form-label">Notice Period (Days)</label>
                                <input type="number" class="form-control @error('notice_period_days') is-invalid @enderror" 
                                       id="notice_period_days" name="notice_period_days" 
                                       value="{{ old('notice_period_days', $grade->notice_period_days ?? 30) }}" 
                                       min="0" max="180">
                                @error('notice_period_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label d-block">&nbsp;</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" 
                                           name="is_active" value="1"
                                           {{ old('is_active', $grade->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($grade) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('hr.grades.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Grade Level Guide</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Suggested grade levels for reference:</p>
                    <ul class="small mb-0">
                        <li><strong>Level 1-3:</strong> Entry Level / Junior</li>
                        <li><strong>Level 4-6:</strong> Mid Level / Associate</li>
                        <li><strong>Level 7-9:</strong> Senior / Lead</li>
                        <li><strong>Level 10-12:</strong> Manager / Principal</li>
                        <li><strong>Level 13+:</strong> Director / Executive</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
