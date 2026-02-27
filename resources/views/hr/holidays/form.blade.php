@extends('layouts.erp')

@section('title', isset($holiday) ? 'Edit Holiday' : 'Add Holiday')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($holiday) ? 'Edit Holiday' : 'Add Holiday' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.holiday-calendars.index') }}">Holidays</a></li>
                <li class="breadcrumb-item active">{{ isset($holiday) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    @include('partials.flash')

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" 
                          action="{{ isset($holiday) ? route('hr.holiday-calendars.update', $holiday) : route('hr.holiday-calendars.store') }}">
                        @csrf
                        @if(isset($holiday))
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label">Holiday Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" 
                                       value="{{ old('name', $holiday->name ?? '') }}" 
                                       maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('holiday_date') is-invalid @enderror" 
                                       id="holiday_date" name="holiday_date" 
                                       value="{{ old('holiday_date', isset($holiday) ? $holiday->holiday_date->format('Y-m-d') : '') }}" 
                                       required>
                                @error('holiday_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="holiday_type" class="form-label">Holiday Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('holiday_type') is-invalid @enderror" 
                                        id="holiday_type" name="holiday_type" required>
                                    <option value="">Select Type</option>
                                    <option value="national" {{ old('holiday_type', $holiday->holiday_type ?? '') == 'national' ? 'selected' : '' }}>National Holiday</option>
                                    <option value="state" {{ old('holiday_type', $holiday->holiday_type ?? '') == 'state' ? 'selected' : '' }}>State Holiday</option>
                                    <option value="religious" {{ old('holiday_type', $holiday->holiday_type ?? '') == 'religious' ? 'selected' : '' }}>Religious Holiday</option>
                                    <option value="company" {{ old('holiday_type', $holiday->holiday_type ?? '') == 'company' ? 'selected' : '' }}>Company Holiday</option>
                                    <option value="optional" {{ old('holiday_type', $holiday->holiday_type ?? '') == 'optional' ? 'selected' : '' }}>Optional Holiday</option>
                                </select>
                                @error('holiday_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">&nbsp;</label>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_optional" 
                                           name="is_optional" value="1"
                                           {{ old('is_optional', $holiday->is_optional ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_optional">Optional Holiday</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" class="form-check-input" id="is_restricted" 
                                           name="is_restricted" value="1"
                                           {{ old('is_restricted', $holiday->is_restricted ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_restricted">Restricted Holiday</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" 
                                      rows="3" maxlength="500">{{ old('description', $holiday->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($holiday) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('hr.holiday-calendars.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Common Indian Holidays</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><strong>National Holidays:</strong></li>
                        <li>• Republic Day (26 Jan)</li>
                        <li>• Independence Day (15 Aug)</li>
                        <li>• Gandhi Jayanti (2 Oct)</li>
                        <li class="mt-3"><strong>Major Festivals:</strong></li>
                        <li>• Holi (March)</li>
                        <li>• Diwali (Oct/Nov)</li>
                        <li>• Eid ul-Fitr (Variable)</li>
                        <li>• Christmas (25 Dec)</li>
                        <li class="mt-3"><strong>Gujarat State Holidays:</strong></li>
                        <li>• Uttarayan (14 Jan)</li>
                        <li>• Gujarat Day (1 May)</li>
                        <li>• Rathyatra (Variable)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
