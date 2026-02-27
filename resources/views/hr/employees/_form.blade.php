@extends('layouts.erp')

@section('title', isset($employee->id) ? 'Edit Employee' : 'Add Employee')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">{{ isset($employee->id) ? 'Edit Employee' : 'Add New Employee' }}</h1>
            <small class="text-muted">
                {{ isset($employee->id) ? $employee->employee_code . ' - ' . $employee->full_name : 'Create a new employee record' }}
            </small>
        </div>
        <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <form action="{{ isset($employee->id) ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" 
          method="POST" enctype="multipart/form-data">
        @csrf
        @if(isset($employee->id))
            @method('PUT')
        @endif

        {{-- Navigation Tabs --}}
        <ul class="nav nav-tabs mb-3" id="employeeTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">
                    <i class="bi bi-person me-1"></i> Basic Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                    <i class="bi bi-geo-alt me-1"></i> Contact & Address
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="identity-tab" data-bs-toggle="tab" data-bs-target="#identity" type="button">
                    <i class="bi bi-card-text me-1"></i> Identity Documents
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button">
                    <i class="bi bi-briefcase me-1"></i> Employment
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="statutory-tab" data-bs-toggle="tab" data-bs-target="#statutory" type="button">
                    <i class="bi bi-bank me-1"></i> Statutory & Bank
                </button>
            </li>
        </ul>

        <div class="tab-content" id="employeeTabContent">
            {{-- Basic Info Tab --}}
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Personal Information</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Photo --}}
                            <div class="col-md-2 text-center">
                                <div class="mb-2">
                                    @if($employee->photo_path)
                                        <img src="{{ Storage::url($employee->photo_path) }}" 
                                             class="rounded-circle border" style="width: 120px; height: 120px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center mx-auto"
                                             style="width: 120px; height: 120px;">
                                            <i class="bi bi-person fs-1 text-muted"></i>
                                        </div>
                                    @endif
                                </div>
                                <input type="file" name="photo" id="photo" class="form-control form-control-sm" accept="image/*">
                                @error('photo')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-10">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Employee Code <span class="text-danger">*</span></label>
                                        <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror"
                                               value="{{ old('employee_code', $employee->employee_code) }}" required>
                                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Biometric ID</label>
                                        <input type="text" name="biometric_id" class="form-control @error('biometric_id') is-invalid @enderror"
                                               value="{{ old('biometric_id', $employee->biometric_id) }}">
                                        @error('biometric_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Card Number</label>
                                        <input type="text" name="card_number" class="form-control @error('card_number') is-invalid @enderror"
                                               value="{{ old('card_number', $employee->card_number) }}">
                                        @error('card_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                                            @foreach($statuses as $value => $label)
                                                <option value="{{ $value }}" {{ old('status', $employee->status?->value ?? 'active') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                                               value="{{ old('first_name', $employee->first_name) }}" required>
                                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" name="middle_name" class="form-control @error('middle_name') is-invalid @enderror"
                                               value="{{ old('middle_name', $employee->middle_name) }}">
                                        @error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                                               value="{{ old('last_name', $employee->last_name) }}" required>
                                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Father's Name</label>
                                <input type="text" name="father_name" class="form-control @error('father_name') is-invalid @enderror"
                                       value="{{ old('father_name', $employee->father_name) }}">
                                @error('father_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control @error('mother_name') is-invalid @enderror"
                                       value="{{ old('mother_name', $employee->mother_name) }}">
                                @error('mother_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Spouse Name</label>
                                <input type="text" name="spouse_name" class="form-control @error('spouse_name') is-invalid @enderror"
                                       value="{{ old('spouse_name', $employee->spouse_name) }}">
                                @error('spouse_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror"
                                       value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}">
                                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                    <option value="">Select</option>
                                    @foreach($genders as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender', $employee->gender) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                    <option value="">Select</option>
                                    @foreach($maritalStatuses as $value => $label)
                                        <option value="{{ $value }}" {{ old('marital_status', $employee->marital_status) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('marital_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select @error('blood_group') is-invalid @enderror">
                                    <option value="">Select</option>
                                    @foreach($bloodGroups as $bg)
                                        <option value="{{ $bg }}" {{ old('blood_group', $employee->blood_group) == $bg ? 'selected' : '' }}>
                                            {{ $bg }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Nationality</label>
                                <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror"
                                       value="{{ old('nationality', $employee->nationality ?? 'Indian') }}">
                                @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Religion</label>
                                <input type="text" name="religion" class="form-control @error('religion') is-invalid @enderror"
                                       value="{{ old('religion', $employee->religion) }}">
                                @error('religion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Caste Category</label>
                                <select name="caste_category" class="form-select @error('caste_category') is-invalid @enderror">
                                    <option value="">Select</option>
                                    <option value="General" {{ old('caste_category', $employee->caste_category) == 'General' ? 'selected' : '' }}>General</option>
                                    <option value="OBC" {{ old('caste_category', $employee->caste_category) == 'OBC' ? 'selected' : '' }}>OBC</option>
                                    <option value="SC" {{ old('caste_category', $employee->caste_category) == 'SC' ? 'selected' : '' }}>SC</option>
                                    <option value="ST" {{ old('caste_category', $employee->caste_category) == 'ST' ? 'selected' : '' }}>ST</option>
                                </select>
                                @error('caste_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact & Address Tab --}}
            <div class="tab-pane fade" id="contact" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Contact Information</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Personal Email</label>
                                        <input type="email" name="personal_email" class="form-control @error('personal_email') is-invalid @enderror"
                                               value="{{ old('personal_email', $employee->personal_email) }}">
                                        @error('personal_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Official Email</label>
                                        <input type="email" id="officialEmail" name="official_email" @if(isset($employee->user_id) && $employee->user_id) required @endif class="form-control @error('official_email') is-invalid @enderror"
                                               value="{{ old('official_email', $employee->official_email) }}">
                                        @error('official_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Personal Mobile</label>
                                        <input type="text" name="personal_mobile" class="form-control @error('personal_mobile') is-invalid @enderror"
                                               value="{{ old('personal_mobile', $employee->personal_mobile) }}">
                                        @error('personal_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Emergency Contact</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Name</label>
                                        <input type="text" name="emergency_contact_name" class="form-control"
                                               value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Phone</label>
                                        <input type="text" name="emergency_contact_phone" class="form-control"
                                               value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Relationship</label>
                                        <input type="text" name="emergency_contact_relation" class="form-control"
                                               value="{{ old('emergency_contact_relation', $employee->emergency_contact_relation) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Present Address</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="present_address" class="form-control" rows="2">{{ old('present_address', $employee->present_address) }}</textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">City</label>
                                        <input type="text" name="present_city" class="form-control"
                                               value="{{ old('present_city', $employee->present_city) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">State</label>
                                        <select name="present_state" class="form-select">
                                            <option value="">Select State</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state }}" {{ old('present_state', $employee->present_state) == $state ? 'selected' : '' }}>
                                                    {{ $state }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Pincode</label>
                                        <input type="text" name="present_pincode" class="form-control"
                                               value="{{ old('present_pincode', $employee->present_pincode) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Permanent Address</h6>
                                <div class="form-check form-check-inline mb-0">
                                    <input type="checkbox" name="address_same_as_present" class="form-check-input" id="sameAddress"
                                           {{ old('address_same_as_present', $employee->address_same_as_present) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="sameAddress">Same as Present</label>
                                </div>
                            </div>
                            <div class="card-body" id="permanentAddressFields">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea name="permanent_address" class="form-control" rows="2">{{ old('permanent_address', $employee->permanent_address) }}</textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">City</label>
                                        <input type="text" name="permanent_city" class="form-control"
                                               value="{{ old('permanent_city', $employee->permanent_city) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">State</label>
                                        <select name="permanent_state" class="form-select">
                                            <option value="">Select State</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state }}" {{ old('permanent_state', $employee->permanent_state) == $state ? 'selected' : '' }}>
                                                    {{ $state }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Pincode</label>
                                        <input type="text" name="permanent_pincode" class="form-control"
                                               value="{{ old('permanent_pincode', $employee->permanent_pincode) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Identity Documents Tab --}}
            <div class="tab-pane fade" id="identity" role="tabpanel">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Government IDs</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">PAN Number</label>
                                <input type="text" name="pan_number" class="form-control text-uppercase @error('pan_number') is-invalid @enderror"
                                       value="{{ old('pan_number', $employee->pan_number) }}" placeholder="ABCDE1234F" maxlength="10">
                                @error('pan_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Aadhar Number</label>
                                <input type="text" name="aadhar_number" class="form-control @error('aadhar_number') is-invalid @enderror"
                                       value="{{ old('aadhar_number', $employee->aadhar_number) }}" placeholder="123412341234" maxlength="12">
                                @error('aadhar_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Voter ID</label>
                                <input type="text" name="voter_id" class="form-control"
                                       value="{{ old('voter_id', $employee->voter_id) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Passport Number</label>
                                <input type="text" name="passport_number" class="form-control"
                                       value="{{ old('passport_number', $employee->passport_number) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Passport Expiry</label>
                                <input type="date" name="passport_expiry" class="form-control"
                                       value="{{ old('passport_expiry', $employee->passport_expiry?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Driving License</label>
                                <input type="text" name="driving_license" class="form-control"
                                       value="{{ old('driving_license', $employee->driving_license) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">DL Expiry</label>
                                <input type="date" name="dl_expiry" class="form-control"
                                       value="{{ old('dl_expiry', $employee->dl_expiry?->format('Y-m-d')) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Qualifications</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Highest Qualification</label>
                                <input type="text" name="highest_qualification" class="form-control"
                                       value="{{ old('highest_qualification', $employee->highest_qualification) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Specialization</label>
                                <input type="text" name="specialization" class="form-control"
                                       value="{{ old('specialization', $employee->specialization) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Experience (Months)</label>
                                <input type="number" name="total_experience_months" class="form-control" min="0"
                                       value="{{ old('total_experience_months', $employee->total_experience_months) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Employment Tab --}}
            <div class="tab-pane fade" id="employment" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Employment Details</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Joining <span class="text-danger">*</span></label>
                                        <input type="date" name="date_of_joining" class="form-control @error('date_of_joining') is-invalid @enderror"
                                               value="{{ old('date_of_joining', $employee->date_of_joining?->format('Y-m-d')) }}" required>
                                        @error('date_of_joining')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirmation Date</label>
                                        <input type="date" name="confirmation_date" class="form-control"
                                               value="{{ old('confirmation_date', $employee->confirmation_date?->format('Y-m-d')) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                                        <select name="employment_type" class="form-select @error('employment_type') is-invalid @enderror" required>
                                            @foreach($employmentTypes as $value => $label)
                                                <option value="{{ $value }}" {{ old('employment_type', $employee->employment_type ?? 'probation') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('employment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Employee Category <span class="text-danger">*</span></label>
                                        <select name="employee_category" class="form-select @error('employee_category') is-invalid @enderror" required>
                                            @foreach($employeeCategories as $value => $label)
                                                <option value="{{ $value }}" {{ old('employee_category', $employee->employee_category ?? 'staff') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('employee_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Probation Period (Months)</label>
                                        <input type="number" name="probation_period_months" class="form-control" min="0" max="24"
                                               value="{{ old('probation_period_months', $employee->probation_period_months ?? 6) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Notice Period (Days)</label>
                                        <input type="number" name="notice_period_days" class="form-control" min="0" max="180"
                                               value="{{ old('notice_period_days', $employee->notice_period_days ?? 30) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Organization Structure</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Department</label>
                                        <select id="departmentSelect" name="department_id" @if(isset($employee->user_id) && $employee->user_id) required @endif class="form-select">
                                            <option value="">Select Department</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ old('department_id', $employee->department_id) == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Designation</label>
                                        <select name="hr_designation_id" class="form-select">
                                            <option value="">Select Designation</option>
                                            @foreach($designations as $desg)
                                                <option value="{{ $desg->id }}" {{ old('hr_designation_id', $employee->hr_designation_id) == $desg->id ? 'selected' : '' }}>
                                                    {{ $desg->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Grade</label>
                                        <select name="hr_grade_id" class="form-select">
                                            <option value="">Select Grade</option>
                                            @foreach($grades as $grade)
                                                <option value="{{ $grade->id }}" {{ old('hr_grade_id', $employee->hr_grade_id) == $grade->id ? 'selected' : '' }}>
                                                    {{ $grade->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Reporting To</label>
                                        <select name="reporting_to" class="form-select">
                                            <option value="">Select Manager</option>
                                            @foreach($managers as $mgr)
                                                @if(!isset($employee->id) || $mgr->id != $employee->id)
                                                    <option value="{{ $mgr->id }}" {{ old('reporting_to', $employee->reporting_to) == $mgr->id ? 'selected' : '' }}>
                                                        {{ $mgr->employee_code }} - {{ $mgr->full_name }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Work Location</label>
                                        <select name="work_location_id" class="form-select">
                                            <option value="">Select Location</option>
                                            @foreach($locations as $loc)
                                                <option value="{{ $loc->id }}" {{ old('work_location_id', $employee->work_location_id) == $loc->id ? 'selected' : '' }}>
                                                    {{ $loc->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cost Center</label>
                                        <input type="text" name="cost_center" class="form-control"
                                               value="{{ old('cost_center', $employee->cost_center) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Attendance & Leave Settings</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Default Shift</label>
                                <select name="default_shift_id" class="form-select">
                                    <option value="">Select Shift</option>
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}" {{ old('default_shift_id', $employee->default_shift_id) == $shift->id ? 'selected' : '' }}>
                                            {{ $shift->name }} ({{ $shift->start_time->format('h:i A') }} - {{ $shift->end_time->format('h:i A') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Attendance Policy</label>
                                <select name="hr_attendance_policy_id" class="form-select">
                                    <option value="">Select Policy</option>
                                    @foreach($attendancePolicies as $policy)
                                        <option value="{{ $policy->id }}" {{ old('hr_attendance_policy_id', $employee->hr_attendance_policy_id) == $policy->id ? 'selected' : '' }}>
                                            {{ $policy->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Leave Policy</label>
                                <select name="hr_leave_policy_id" class="form-select">
                                    <option value="">Select Policy</option>
                                    @foreach($leavePolicies as $policy)
                                        <option value="{{ $policy->id }}" {{ old('hr_leave_policy_id', $employee->hr_leave_policy_id) == $policy->id ? 'selected' : '' }}>
                                            {{ $policy->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Attendance Mode</label>
                                <select name="attendance_mode" class="form-select">
                                    <option value="both" {{ old('attendance_mode', $employee->attendance_mode ?? 'both') == 'both' ? 'selected' : '' }}>Both</option>
                                    <option value="biometric" {{ old('attendance_mode', $employee->attendance_mode) == 'biometric' ? 'selected' : '' }}>Biometric Only</option>
                                    <option value="manual" {{ old('attendance_mode', $employee->attendance_mode) == 'manual' ? 'selected' : '' }}>Manual Only</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="overtime_applicable" class="form-check-input" id="otApplicable"
                                           {{ old('overtime_applicable', $employee->overtime_applicable ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="otApplicable">Overtime Applicable</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Statutory & Bank Tab --}}
            <div class="tab-pane fade" id="statutory" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Statutory Compliance</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    {{-- PF --}}
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" name="pf_applicable" class="form-check-input" id="pfApplicable"
                                                   {{ old('pf_applicable', $employee->pf_applicable ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="pfApplicable">PF Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">PF/UAN Number</label>
                                        <input type="text" name="pf_number" class="form-control"
                                               value="{{ old('pf_number', $employee->pf_number) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">PF Join Date</label>
                                        <input type="date" name="pf_join_date" class="form-control"
                                               value="{{ old('pf_join_date', $employee->pf_join_date?->format('Y-m-d')) }}">
                                    </div>

                                    {{-- ESI --}}
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" name="esi_applicable" class="form-check-input" id="esiApplicable"
                                                   {{ old('esi_applicable', $employee->esi_applicable) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="esiApplicable">ESI Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ESI Number</label>
                                        <input type="text" name="esi_number" class="form-control"
                                               value="{{ old('esi_number', $employee->esi_number) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ESI Join Date</label>
                                        <input type="date" name="esi_join_date" class="form-control"
                                               value="{{ old('esi_join_date', $employee->esi_join_date?->format('Y-m-d')) }}">
                                    </div>

                                    {{-- PT --}}
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" name="pt_applicable" class="form-check-input" id="ptApplicable"
                                                   {{ old('pt_applicable', $employee->pt_applicable ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="ptApplicable">PT Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">PT State</label>
                                        <select name="pt_state" class="form-select">
                                            <option value="">Select State</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state }}" {{ old('pt_state', $employee->pt_state) == $state ? 'selected' : '' }}>
                                                    {{ $state }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input type="checkbox" name="lwf_applicable" class="form-check-input" id="lwfApplicable"
                                                   {{ old('lwf_applicable', $employee->lwf_applicable) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="lwfApplicable">LWF Applicable</label>
                                        </div>
                                    </div>

                                    {{-- TDS --}}
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" name="tds_applicable" class="form-check-input" id="tdsApplicable"
                                                   {{ old('tds_applicable', $employee->tds_applicable ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="tdsApplicable">TDS Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tax Regime</label>
                                        <select name="tax_regime" class="form-select">
                                            <option value="new" {{ old('tax_regime', $employee->tax_regime ?? 'new') == 'new' ? 'selected' : '' }}>New Regime</option>
                                            <option value="old" {{ old('tax_regime', $employee->tax_regime) == 'old' ? 'selected' : '' }}>Old Regime</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input type="checkbox" name="gratuity_applicable" class="form-check-input" id="gratuityApplicable"
                                                   {{ old('gratuity_applicable', $employee->gratuity_applicable ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="gratuityApplicable">Gratuity Applicable</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Bank Details</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" name="bank_name" class="form-control"
                                               value="{{ old('bank_name', $employee->bank_name) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Branch</label>
                                        <input type="text" name="bank_branch" class="form-control"
                                               value="{{ old('bank_branch', $employee->bank_branch) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Account Number</label>
                                        <input type="text" name="bank_account_number" class="form-control"
                                               value="{{ old('bank_account_number', $employee->bank_account_number) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">IFSC Code</label>
                                        <input type="text" name="bank_ifsc" class="form-control text-uppercase"
                                               value="{{ old('bank_ifsc', $employee->bank_ifsc) }}" maxlength="11">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Account Type</label>
                                        <select name="bank_account_type" class="form-select">
                                            <option value="savings" {{ old('bank_account_type', $employee->bank_account_type ?? 'savings') == 'savings' ? 'selected' : '' }}>Savings</option>
                                            <option value="current" {{ old('bank_account_type', $employee->bank_account_type) == 'current' ? 'selected' : '' }}>Current</option>
                                            <option value="salary" {{ old('bank_account_type', $employee->bank_account_type) == 'salary' ? 'selected' : '' }}>Salary</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Mode</label>
                                        <select name="payment_mode" class="form-select">
                                            <option value="bank_transfer" {{ old('payment_mode', $employee->payment_mode ?? 'bank_transfer') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="cheque" {{ old('payment_mode', $employee->payment_mode) == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                            <option value="cash" {{ old('payment_mode', $employee->payment_mode) == 'cash' ? 'selected' : '' }}>Cash</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Salary Structure</h6></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Salary Structure</label>
                                        <select name="hr_salary_structure_id" class="form-select">
                                            <option value="">Select Salary Structure</option>
                                            @foreach($salaryStructures as $struct)
                                                <option value="{{ $struct->id }}" {{ old('hr_salary_structure_id', $employee->hr_salary_structure_id) == $struct->id ? 'selected' : '' }}>
                                                    {{ $struct->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Salary details can be added after creating the employee</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit Buttons --}}
        <div class="card">
            <div class="card-body d-flex justify-content-between">
                <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <div>
                    @if(!isset($employee->id) || empty($employee->user_id))
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="create_user_account" class="form-check-input" id="createUser"
                                   {{ old('create_user_account', !isset($employee->id)) ? 'checked' : '' }}>
                            <label class="form-check-label" for="createUser">Create User Account</label>
                            <span class="text-muted small ms-2">(Uses Official Email and sets Primary Department)</span>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> {{ isset($employee->id) ? 'Update Employee' : 'Create Employee' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Same address checkbox
    const sameAddressCheck = document.getElementById('sameAddress');
    const permanentFields = document.getElementById('permanentAddressFields');
    
    if (sameAddressCheck) {
        sameAddressCheck.addEventListener('change', function() {
            permanentFields.style.display = this.checked ? 'none' : 'block';
        });
        
        // Initial state
        if (sameAddressCheck.checked) {
            permanentFields.style.display = 'none';
        }
    }

    // Create User Account checkbox -> require Official Email + Department
    const createUserCheck = document.getElementById('createUser');
    const officialEmailInput = document.getElementById('officialEmail');
    const departmentSelect = document.getElementById('departmentSelect');

    function toggleUserProvisioningRequirements() {
        if (!createUserCheck || !officialEmailInput || !departmentSelect) return;

        if (createUserCheck.checked) {
            officialEmailInput.setAttribute('required', 'required');
            departmentSelect.setAttribute('required', 'required');
        } else {
            // Keep required if employee already has a linked user (server-side validation enforces it too)
            officialEmailInput.removeAttribute('required');
            departmentSelect.removeAttribute('required');
        }
    }

    if (createUserCheck) {
        createUserCheck.addEventListener('change', toggleUserProvisioningRequirements);
        toggleUserProvisioningRequirements();
    }

});
</script>
@endpush
