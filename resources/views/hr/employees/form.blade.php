@extends('layouts.erp')

@section('title', isset($employee->id) ? 'Edit Employee' : 'Add Employee')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">{{ isset($employee->id) ? 'Edit Employee' : 'Add New Employee' }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.employees.index') }}">Employees</a></li>
                    <li class="breadcrumb-item active">{{ isset($employee->id) ? $employee->employee_code : 'New' }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <form action="{{ isset($employee->id) ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" 
          method="POST" enctype="multipart/form-data" id="employeeForm">
        @csrf
        @if(isset($employee->id))
            @method('PUT')
        @endif

        {{-- Tab Navigation --}}
        <ul class="nav nav-tabs mb-3" id="employeeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">
                    <i class="bi bi-person me-1"></i> Basic Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                    <i class="bi bi-telephone me-1"></i> Contact & Address
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button">
                    <i class="bi bi-file-earmark me-1"></i> Identity Documents
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

        {{-- Tab Content --}}
        <div class="tab-content" id="employeeTabsContent">
            {{-- Basic Info Tab --}}
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- Photo --}}
                            <div class="col-md-3 text-center">
                                <div class="mb-3">
                                    @if($employee->photo_path)
                                        <img src="{{ Storage::url($employee->photo_path) }}" class="img-thumbnail rounded-circle" width="150" height="150" id="photoPreview">
                                    @else
                                        <div class="bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px; font-size: 3rem;" id="photoPlaceholder">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <img src="" class="img-thumbnail rounded-circle d-none" width="150" height="150" id="photoPreview">
                                    @endif
                                </div>
                                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" id="photoInput">
                                <small class="text-muted">Max 2MB, JPG/PNG</small>
                            </div>

                            <div class="col-md-9">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Employee Code <span class="text-danger">*</span></label>
                                        <input type="text" name="employee_code" class="form-control @error('employee_code') is-invalid @enderror" 
                                               value="{{ old('employee_code', $employee->employee_code) }}" required>
                                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Biometric ID</label>
                                        <input type="text" name="biometric_id" class="form-control" value="{{ old('biometric_id', $employee->biometric_id) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Card Number</label>
                                        <input type="text" name="card_number" class="form-control" value="{{ old('card_number', $employee->card_number) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" 
                                               value="{{ old('first_name', $employee->first_name) }}" required>
                                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" name="middle_name" class="form-control" value="{{ old('middle_name', $employee->middle_name) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" 
                                               value="{{ old('last_name', $employee->last_name) }}" required>
                                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Father's Name</label>
                                        <input type="text" name="father_name" class="form-control" value="{{ old('father_name', $employee->father_name) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Mother's Name</label>
                                        <input type="text" name="mother_name" class="form-control" value="{{ old('mother_name', $employee->mother_name) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Spouse Name</label>
                                        <input type="text" name="spouse_name" class="form-control" value="{{ old('spouse_name', $employee->spouse_name) }}">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select</option>
                                    @foreach($genders as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender', $employee->gender) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-select">
                                    <option value="">Select</option>
                                    @foreach($maritalStatuses as $value => $label)
                                        <option value="{{ $value }}" {{ old('marital_status', $employee->marital_status) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="">Select</option>
                                    @foreach($bloodGroups as $bg)
                                        <option value="{{ $bg }}" {{ old('blood_group', $employee->blood_group) == $bg ? 'selected' : '' }}>{{ $bg }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nationality</label>
                                <input type="text" name="nationality" class="form-control" value="{{ old('nationality', $employee->nationality ?? 'Indian') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Religion</label>
                                <input type="text" name="religion" class="form-control" value="{{ old('religion', $employee->religion) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select name="caste_category" class="form-select">
                                    <option value="">Select</option>
                                    <option value="General" {{ old('caste_category', $employee->caste_category) == 'General' ? 'selected' : '' }}>General</option>
                                    <option value="OBC" {{ old('caste_category', $employee->caste_category) == 'OBC' ? 'selected' : '' }}>OBC</option>
                                    <option value="SC" {{ old('caste_category', $employee->caste_category) == 'SC' ? 'selected' : '' }}>SC</option>
                                    <option value="ST" {{ old('caste_category', $employee->caste_category) == 'ST' ? 'selected' : '' }}>ST</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Highest Qualification</label>
                                <input type="text" name="highest_qualification" class="form-control" value="{{ old('highest_qualification', $employee->highest_qualification) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact & Address Tab --}}
            <div class="tab-pane fade" id="contact" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h6 class="border-bottom pb-2 mb-3">Contact Information</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Personal Email</label>
                                <input type="email" name="personal_email" class="form-control" value="{{ old('personal_email', $employee->personal_email) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Official Email</label>
                                <input type="email" name="official_email" class="form-control" value="{{ old('official_email', $employee->official_email) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mobile Number</label>
                                <input type="text" name="personal_mobile" class="form-control" value="{{ old('personal_mobile', $employee->personal_mobile) }}">
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Emergency Contact</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Contact Name</label>
                                <input type="text" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" name="emergency_contact_phone" class="form-control" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Relationship</label>
                                <input type="text" name="emergency_contact_relation" class="form-control" value="{{ old('emergency_contact_relation', $employee->emergency_contact_relation) }}">
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Present Address</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="present_address" class="form-control" rows="2">{{ old('present_address', $employee->present_address) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" name="present_city" class="form-control" value="{{ old('present_city', $employee->present_city) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <select name="present_state" class="form-select">
                                    <option value="">Select State</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state }}" {{ old('present_state', $employee->present_state) == $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">PIN Code</label>
                                <input type="text" name="present_pincode" class="form-control" value="{{ old('present_pincode', $employee->present_pincode) }}">
                            </div>
                        </div>

                        <div class="form-check my-3">
                            <input type="checkbox" class="form-check-input" name="address_same_as_present" id="addressSame" value="1" 
                                   {{ old('address_same_as_present', $employee->address_same_as_present) ? 'checked' : '' }}>
                            <label class="form-check-label" for="addressSame">Permanent address same as present</label>
                        </div>

                        <div id="permanentAddressSection" class="{{ old('address_same_as_present', $employee->address_same_as_present) ? 'd-none' : '' }}">
                            <h6 class="border-bottom pb-2 mb-3">Permanent Address</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="permanent_address" class="form-control" rows="2">{{ old('permanent_address', $employee->permanent_address) }}</textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input type="text" name="permanent_city" class="form-control" value="{{ old('permanent_city', $employee->permanent_city) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <select name="permanent_state" class="form-select">
                                        <option value="">Select State</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state }}" {{ old('permanent_state', $employee->permanent_state) == $state ? 'selected' : '' }}>{{ $state }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">PIN Code</label>
                                    <input type="text" name="permanent_pincode" class="form-control" value="{{ old('permanent_pincode', $employee->permanent_pincode) }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Identity Documents Tab --}}
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">PAN Number</label>
                                <input type="text" name="pan_number" class="form-control text-uppercase @error('pan_number') is-invalid @enderror" 
                                       value="{{ old('pan_number', $employee->pan_number) }}" placeholder="ABCDE1234F" maxlength="10">
                                @error('pan_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Aadhar Number</label>
                                <input type="text" name="aadhar_number" class="form-control @error('aadhar_number') is-invalid @enderror" 
                                       value="{{ old('aadhar_number', $employee->aadhar_number) }}" placeholder="1234 5678 9012" maxlength="12">
                                @error('aadhar_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Voter ID</label>
                                <input type="text" name="voter_id" class="form-control" value="{{ old('voter_id', $employee->voter_id) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Passport Number</label>
                                <input type="text" name="passport_number" class="form-control" value="{{ old('passport_number', $employee->passport_number) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Passport Expiry</label>
                                <input type="date" name="passport_expiry" class="form-control" value="{{ old('passport_expiry', $employee->passport_expiry?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4"></div>
                            <div class="col-md-4">
                                <label class="form-label">Driving License</label>
                                <input type="text" name="driving_license" class="form-control" value="{{ old('driving_license', $employee->driving_license) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">DL Expiry</label>
                                <input type="date" name="dl_expiry" class="form-control" value="{{ old('dl_expiry', $employee->dl_expiry?->format('Y-m-d')) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Employment Tab --}}
            <div class="tab-pane fade" id="employment" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h6 class="border-bottom pb-2 mb-3">Employment Details</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date of Joining <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_joining" class="form-control @error('date_of_joining') is-invalid @enderror" 
                                       value="{{ old('date_of_joining', $employee->date_of_joining?->format('Y-m-d')) }}" required>
                                @error('date_of_joining')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Confirmation Date</label>
                                <input type="date" name="confirmation_date" class="form-control" 
                                       value="{{ old('confirmation_date', $employee->confirmation_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Employment Type <span class="text-danger">*</span></label>
                                <select name="employment_type" class="form-select" required>
                                    @foreach($employmentTypes as $value => $label)
                                        <option value="{{ $value }}" {{ old('employment_type', $employee->employment_type ?? 'probation') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Employee Category <span class="text-danger">*</span></label>
                                <select name="employee_category" class="form-select" required>
                                    @foreach($employeeCategories as $value => $label)
                                        <option value="{{ $value }}" {{ old('employee_category', $employee->employee_category ?? 'staff') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Probation Period (Months)</label>
                                <input type="number" name="probation_period_months" class="form-control" min="0" max="24"
                                       value="{{ old('probation_period_months', $employee->probation_period_months ?? 6) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Notice Period (Days)</label>
                                <input type="number" name="notice_period_days" class="form-control" min="0" max="180"
                                       value="{{ old('notice_period_days', $employee->notice_period_days ?? 30) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Total Experience (Months)</label>
                                <input type="number" name="total_experience_months" class="form-control" min="0"
                                       value="{{ old('total_experience_months', $employee->total_experience_months ?? 0) }}">
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Position & Reporting</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-select">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $employee->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Designation</label>
                                <select name="hr_designation_id" class="form-select">
                                    <option value="">Select Designation</option>
                                    @foreach($designations as $desig)
                                        <option value="{{ $desig->id }}" {{ old('hr_designation_id', $employee->hr_designation_id) == $desig->id ? 'selected' : '' }}>{{ $desig->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grade</label>
                                <select name="hr_grade_id" class="form-select">
                                    <option value="">Select Grade</option>
                                    @foreach($grades as $grade)
                                        <option value="{{ $grade->id }}" {{ old('hr_grade_id', $employee->hr_grade_id) == $grade->id ? 'selected' : '' }}>{{ $grade->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reporting Manager</label>
                                <select name="reporting_to" class="form-select">
                                    <option value="">Select Manager</option>
                                    @foreach($managers as $mgr)
                                        @if(!isset($employee->id) || $mgr->id != $employee->id)
                                            <option value="{{ $mgr->id }}" {{ old('reporting_to', $employee->reporting_to) == $mgr->id ? 'selected' : '' }}>{{ $mgr->employee_code }} - {{ $mgr->full_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Work Location</label>
                                <select name="work_location_id" class="form-select">
                                    <option value="">Select Location</option>
                                    @foreach($locations as $loc)
                                        <option value="{{ $loc->id }}" {{ old('work_location_id', $employee->work_location_id) == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cost Center</label>
                                <input type="text" name="cost_center" class="form-control" value="{{ old('cost_center', $employee->cost_center) }}">
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Attendance & Leave</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Default Shift</label>
                                <select name="default_shift_id" class="form-select">
                                    <option value="">Select Shift</option>
                                    @foreach($shifts as $shift)
                                        <option value="{{ $shift->id }}" {{ old('default_shift_id', $employee->default_shift_id) == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Attendance Policy</label>
                                <select name="hr_attendance_policy_id" class="form-select">
                                    <option value="">Select Policy</option>
                                    @foreach($attendancePolicies as $policy)
                                        <option value="{{ $policy->id }}" {{ old('hr_attendance_policy_id', $employee->hr_attendance_policy_id) == $policy->id ? 'selected' : '' }}>{{ $policy->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Leave Policy</label>
                                <select name="hr_leave_policy_id" class="form-select">
                                    <option value="">Select Policy</option>
                                    @foreach($leavePolicies as $policy)
                                        <option value="{{ $policy->id }}" {{ old('hr_leave_policy_id', $employee->hr_leave_policy_id) == $policy->id ? 'selected' : '' }}>{{ $policy->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Attendance Mode</label>
                                <select name="attendance_mode" class="form-select">
                                    <option value="biometric" {{ old('attendance_mode', $employee->attendance_mode) == 'biometric' ? 'selected' : '' }}>Biometric</option>
                                    <option value="manual" {{ old('attendance_mode', $employee->attendance_mode) == 'manual' ? 'selected' : '' }}>Manual</option>
                                    <option value="both" {{ old('attendance_mode', $employee->attendance_mode) == 'both' ? 'selected' : '' }}>Both</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="overtime_applicable" id="otApplicable" value="1"
                                           {{ old('overtime_applicable', $employee->overtime_applicable) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="otApplicable">Overtime Applicable</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Statutory & Bank Tab --}}
            <div class="tab-pane fade" id="statutory" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h6 class="border-bottom pb-2 mb-3">Statutory Compliance</h6>
                        <div class="row g-3">
                            {{-- PF --}}
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="pf_applicable" id="pfApplicable" value="1"
                                           {{ old('pf_applicable', $employee->pf_applicable) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-medium" for="pfApplicable">PF Applicable</label>
                                </div>
                            </div>
                            <div class="col-md-4 pf-fields {{ old('pf_applicable', $employee->pf_applicable) ? '' : 'd-none' }}">
                                <label class="form-label">UAN / PF Number</label>
                                <input type="text" name="pf_number" class="form-control" value="{{ old('pf_number', $employee->pf_number) }}">
                            </div>
                            <div class="col-md-4 pf-fields {{ old('pf_applicable', $employee->pf_applicable) ? '' : 'd-none' }}">
                                <label class="form-label">PF Join Date</label>
                                <input type="date" name="pf_join_date" class="form-control" value="{{ old('pf_join_date', $employee->pf_join_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4 pf-fields {{ old('pf_applicable', $employee->pf_applicable) ? '' : 'd-none' }}">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="eps_applicable" id="epsApplicable" value="1"
                                           {{ old('eps_applicable', $employee->eps_applicable ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="epsApplicable">EPS Applicable</label>
                                </div>
                            </div>

                            {{-- ESI --}}
                            <div class="col-md-12 mt-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="esi_applicable" id="esiApplicable" value="1"
                                           {{ old('esi_applicable', $employee->esi_applicable) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-medium" for="esiApplicable">ESI Applicable</label>
                                </div>
                            </div>
                            <div class="col-md-4 esi-fields {{ old('esi_applicable', $employee->esi_applicable) ? '' : 'd-none' }}">
                                <label class="form-label">ESI Number</label>
                                <input type="text" name="esi_number" class="form-control" value="{{ old('esi_number', $employee->esi_number) }}">
                            </div>
                            <div class="col-md-4 esi-fields {{ old('esi_applicable', $employee->esi_applicable) ? '' : 'd-none' }}">
                                <label class="form-label">ESI Join Date</label>
                                <input type="date" name="esi_join_date" class="form-control" value="{{ old('esi_join_date', $employee->esi_join_date?->format('Y-m-d')) }}">
                            </div>

                            {{-- PT --}}
                            <div class="col-md-12 mt-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="pt_applicable" id="ptApplicable" value="1"
                                           {{ old('pt_applicable', $employee->pt_applicable) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-medium" for="ptApplicable">Professional Tax Applicable</label>
                                </div>
                            </div>
                            <div class="col-md-4 pt-fields {{ old('pt_applicable', $employee->pt_applicable) ? '' : 'd-none' }}">
                                <label class="form-label">PT State</label>
                                <select name="pt_state" class="form-select">
                                    <option value="">Select State</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state }}" {{ old('pt_state', $employee->pt_state) == $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Other --}}
                            <div class="col-md-12 mt-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="lwf_applicable" id="lwfApplicable" value="1"
                                                   {{ old('lwf_applicable', $employee->lwf_applicable) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="lwfApplicable">LWF Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="tds_applicable" id="tdsApplicable" value="1"
                                                   {{ old('tds_applicable', $employee->tds_applicable ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="tdsApplicable">TDS Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="gratuity_applicable" id="gratuityApplicable" value="1"
                                                   {{ old('gratuity_applicable', $employee->gratuity_applicable) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="gratuityApplicable">Gratuity Applicable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tax Regime</label>
                                        <select name="tax_regime" class="form-select">
                                            <option value="new" {{ old('tax_regime', $employee->tax_regime ?? 'new') == 'new' ? 'selected' : '' }}>New Regime</option>
                                            <option value="old" {{ old('tax_regime', $employee->tax_regime) == 'old' ? 'selected' : '' }}>Old Regime</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 mt-4">Bank Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $employee->bank_name) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Branch Name</label>
                                <input type="text" name="bank_branch" class="form-control" value="{{ old('bank_branch', $employee->bank_branch) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Account Type</label>
                                <select name="bank_account_type" class="form-select">
                                    <option value="savings" {{ old('bank_account_type', $employee->bank_account_type) == 'savings' ? 'selected' : '' }}>Savings</option>
                                    <option value="current" {{ old('bank_account_type', $employee->bank_account_type) == 'current' ? 'selected' : '' }}>Current</option>
                                    <option value="salary" {{ old('bank_account_type', $employee->bank_account_type) == 'salary' ? 'selected' : '' }}>Salary Account</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $employee->bank_account_number) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IFSC Code</label>
                                <input type="text" name="bank_ifsc" class="form-control text-uppercase" value="{{ old('bank_ifsc', $employee->bank_ifsc) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Mode</label>
                                <select name="payment_mode" class="form-select">
                                    <option value="bank_transfer" {{ old('payment_mode', $employee->payment_mode ?? 'bank_transfer') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="cheque" {{ old('payment_mode', $employee->payment_mode) == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                    <option value="cash" {{ old('payment_mode', $employee->payment_mode) == 'cash' ? 'selected' : '' }}>Cash</option>
                                </select>
                            </div>
                        </div>

                        @if(!isset($employee->id))
                        <h6 class="border-bottom pb-2 mb-3 mt-4">User Account</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="create_user_account" id="createUserAccount" value="1">
                                    <label class="form-check-label" for="createUserAccount">
                                        Create login account for this employee (requires official email)
                                    </label>
                                </div>
                                <small class="text-muted">Default password will be "password123" - employee should change it on first login.</small>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit Buttons --}}
        <div class="card mt-3">
            <div class="card-body d-flex justify-content-between">
                <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </a>
                <div>
                    <button type="submit" name="action" value="save_continue" class="btn btn-secondary me-2">
                        <i class="bi bi-check me-1"></i> Save & Add Another
                    </button>
                    <button type="submit" name="action" value="save" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Employee
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Photo preview
    document.getElementById('photoInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photoPreview').src = e.target.result;
                document.getElementById('photoPreview').classList.remove('d-none');
                const placeholder = document.getElementById('photoPlaceholder');
                if (placeholder) placeholder.classList.add('d-none');
            }
            reader.readAsDataURL(file);
        }
    });

    // Address same as present toggle
    document.getElementById('addressSame').addEventListener('change', function() {
        document.getElementById('permanentAddressSection').classList.toggle('d-none', this.checked);
    });

    // PF fields toggle
    document.getElementById('pfApplicable').addEventListener('change', function() {
        document.querySelectorAll('.pf-fields').forEach(el => el.classList.toggle('d-none', !this.checked));
    });

    // ESI fields toggle
    document.getElementById('esiApplicable').addEventListener('change', function() {
        document.querySelectorAll('.esi-fields').forEach(el => el.classList.toggle('d-none', !this.checked));
    });

    // PT fields toggle
    document.getElementById('ptApplicable').addEventListener('change', function() {
        document.querySelectorAll('.pt-fields').forEach(el => el.classList.toggle('d-none', !this.checked));
    });
});
</script>
@endpush
@endsection
