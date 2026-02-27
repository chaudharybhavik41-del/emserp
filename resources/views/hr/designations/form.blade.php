@extends('layouts.erp')

@section('title', isset($designation) ? 'Edit Designation' : 'Add Designation')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($designation) ? 'Edit Designation' : 'Add Designation' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.designations.index') }}">Designations</a></li>
                <li class="breadcrumb-item active">{{ isset($designation) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" 
                          action="{{ isset($designation) ? route('hr.designations.update', $designation) : route('hr.designations.store') }}">
                        @csrf
                        @if(isset($designation))
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" 
                                       value="{{ old('code', $designation->code ?? '') }}" 
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $designation->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="department_id" class="form-label">Department</label>
                                <select class="form-select @error('department_id') is-invalid @enderror" 
                                        id="department_id" name="department_id">
                                    <option value="">-- Select Department --</option>
                                    @foreach($departments ?? [] as $dept)
                                        <option value="{{ $dept->id }}" 
                                            {{ old('department_id', $designation->department_id ?? '') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="hr_grade_id" class="form-label">Grade</label>
                                <select class="form-select @error('hr_grade_id') is-invalid @enderror" 
                                        id="hr_grade_id" name="hr_grade_id">
                                    <option value="">-- Select Grade --</option>
                                    @foreach($grades ?? [] as $grade)
                                        <option value="{{ $grade->id }}" 
                                            {{ old('hr_grade_id', $designation->hr_grade_id ?? '') == $grade->id ? 'selected' : '' }}>
                                            {{ $grade->name }} (Level {{ $grade->level }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('hr_grade_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="2" maxlength="500">{{ old('description', $designation->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Salary Range</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="min_salary" class="form-label">Minimum Salary (₹)</label>
                                <input type="number" class="form-control @error('min_salary') is-invalid @enderror" 
                                       id="min_salary" name="min_salary" 
                                       value="{{ old('min_salary', $designation->min_salary ?? '') }}" 
                                       min="0" step="0.01">
                                @error('min_salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="max_salary" class="form-label">Maximum Salary (₹)</label>
                                <input type="number" class="form-control @error('max_salary') is-invalid @enderror" 
                                       id="max_salary" name="max_salary" 
                                       value="{{ old('max_salary', $designation->max_salary ?? '') }}" 
                                       min="0" step="0.01">
                                @error('max_salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Options</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                       id="sort_order" name="sort_order" 
                                       value="{{ old('sort_order', $designation->sort_order ?? 0) }}" 
                                       min="0">
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label d-block">&nbsp;</label>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_supervisory" 
                                           name="is_supervisory" value="1"
                                           {{ old('is_supervisory', $designation->is_supervisory ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_supervisory">Supervisory Role</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_managerial" 
                                           name="is_managerial" value="1"
                                           {{ old('is_managerial', $designation->is_managerial ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_managerial">Managerial Role</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_active" 
                                           name="is_active" value="1"
                                           {{ old('is_active', $designation->is_active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($designation) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('hr.designations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
