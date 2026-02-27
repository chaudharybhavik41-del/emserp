@extends('layouts.erp')

@section('title', $employee->full_name)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $employee->full_name }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.employees.index') }}">Employees</a></li>
                    <li class="breadcrumb-item active">{{ $employee->employee_code }}</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            @can('hr.employee.update')
                <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('hr.employees.id-card', $employee) }}" class="btn btn-outline-primary">
                <i class="bi bi-person-badge me-1"></i> ID Card
            </a>
            <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        {{-- Left Column - Profile Card --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    @if($employee->photo_path)
                        <img src="{{ Storage::url($employee->photo_path) }}" class="rounded-circle mb-3" width="120" height="120" alt="">
                    @else
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                            {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                        </div>
                    @endif
                    <h5 class="mb-1">{{ $employee->full_name }}</h5>
                    <p class="text-muted mb-2">{{ $employee->designation?->name ?? 'N/A' }}</p>
                    <span class="badge bg-{{ $employee->status->color() }} mb-3">{{ $employee->status->label() }}</span>

                    <hr>

                    <div class="text-start">
                        <p class="mb-2">
                            <i class="bi bi-hash text-muted me-2"></i>
                            <strong>Emp. Code:</strong> {{ $employee->employee_code }}
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-building text-muted me-2"></i>
                            <strong>Department:</strong> {{ $employee->department?->name ?? 'N/A' }}
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-calendar-event text-muted me-2"></i>
                            <strong>Joined:</strong> {{ $employee->date_of_joining?->format('d M Y') }}
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-clock text-muted me-2"></i>
                            <strong>Service:</strong> {{ $employee->service_years }} yrs {{ $employee->service_months % 12 }} months
                        </p>
                        @if($employee->reporting_to)
                            <p class="mb-2">
                                <i class="bi bi-person text-muted me-2"></i>
                                <strong>Reports To:</strong> 
                                <a href="{{ route('hr.employees.show', $employee->reportingManager) }}">
                                    {{ $employee->reportingManager->full_name }}
                                </a>
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('hr.employees.salary.show', $employee) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-currency-rupee me-1"></i> Salary Details
                        </a>
                        <a href="{{ route('hr.employees.leave-balance', $employee) }}" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-calendar-check me-1"></i> Leave Balance
                        </a>
                        <a href="{{ route('hr.attendance.monthly', ['employee_id' => $employee->id]) }}" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-calendar3 me-1"></i> Attendance Calendar
                        </a>
                        <a href="{{ route('hr.payroll.index', ['employee_id' => $employee->id]) }}" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-file-earmark-text me-1"></i> Payroll History
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column - Details --}}
        <div class="col-md-8">
            {{-- Tab Navigation --}}
            <ul class="nav nav-tabs" id="employeeDetailTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#personal">Personal</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#employment">Employment</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#statutory">Statutory</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bank">Bank</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents">Documents</button>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Personal Tab --}}
                <div class="tab-pane fade show active" id="personal">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted">Full Name</small>
                                    <p class="mb-0 fw-medium">{{ $employee->full_name }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Father's Name</small>
                                    <p class="mb-0">{{ $employee->father_name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Mother's Name</small>
                                    <p class="mb-0">{{ $employee->mother_name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Spouse Name</small>
                                    <p class="mb-0">{{ $employee->spouse_name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Date of Birth</small>
                                    <p class="mb-0">{{ $employee->date_of_birth?->format('d M Y') ?? '-' }} 
                                        @if($employee->age)({{ $employee->age }} years)@endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Gender</small>
                                    <p class="mb-0">{{ ucfirst($employee->gender ?? '-') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Marital Status</small>
                                    <p class="mb-0">{{ ucfirst($employee->marital_status ?? '-') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Blood Group</small>
                                    <p class="mb-0">{{ $employee->blood_group ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Nationality</small>
                                    <p class="mb-0">{{ $employee->nationality ?? '-' }}</p>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-3">Contact Information</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted">Personal Email</small>
                                    <p class="mb-0">{{ $employee->personal_email ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Official Email</small>
                                    <p class="mb-0">{{ $employee->official_email ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Mobile</small>
                                    <p class="mb-0">{{ $employee->personal_mobile ?? '-' }}</p>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-3">Present Address</h6>
                            <p class="mb-1">{{ $employee->present_address ?? '-' }}</p>
                            <p class="mb-0">{{ $employee->present_city }}, {{ $employee->present_state }} - {{ $employee->present_pincode }}</p>

                            @if(!$employee->address_same_as_present)
                                <hr>
                                <h6 class="mb-3">Permanent Address</h6>
                                <p class="mb-1">{{ $employee->permanent_address ?? '-' }}</p>
                                <p class="mb-0">{{ $employee->permanent_city }}, {{ $employee->permanent_state }} - {{ $employee->permanent_pincode }}</p>
                            @endif

                            <hr>
                            <h6 class="mb-3">Emergency Contact</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted">Name</small>
                                    <p class="mb-0">{{ $employee->emergency_contact_name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Phone</small>
                                    <p class="mb-0">{{ $employee->emergency_contact_phone ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Relation</small>
                                    <p class="mb-0">{{ $employee->emergency_contact_relation ?? '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Employment Tab --}}
                <div class="tab-pane fade" id="employment">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted">Employee Code</small>
                                    <p class="mb-0 fw-medium">{{ $employee->employee_code }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Biometric ID</small>
                                    <p class="mb-0">{{ $employee->biometric_id ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Card Number</small>
                                    <p class="mb-0">{{ $employee->card_number ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Date of Joining</small>
                                    <p class="mb-0">{{ $employee->date_of_joining?->format('d M Y') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Confirmation Date</small>
                                    <p class="mb-0">
                                        @if($employee->confirmation_date)
                                            {{ $employee->confirmation_date->format('d M Y') }}
                                        @elseif($employee->is_on_probation)
                                            <span class="text-warning">On Probation (Ends: {{ $employee->probation_end_date?->format('d M Y') }})</span>
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Employment Type</small>
                                    <p class="mb-0">{{ ucfirst($employee->employment_type ?? '-') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Department</small>
                                    <p class="mb-0">{{ $employee->department?->name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Designation</small>
                                    <p class="mb-0">{{ $employee->designation?->name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Grade</small>
                                    <p class="mb-0">{{ $employee->grade?->name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Work Location</small>
                                    <p class="mb-0">{{ $employee->workLocation?->name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Default Shift</small>
                                    <p class="mb-0">{{ $employee->defaultShift?->name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Overtime Applicable</small>
                                    <p class="mb-0">{{ $employee->overtime_applicable ? 'Yes' : 'No' }}</p>
                                </div>
                            </div>

                            @if($employee->date_of_leaving)
                                <hr>
                                <h6 class="text-danger mb-3">Separation Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <small class="text-muted">Date of Leaving</small>
                                        <p class="mb-0">{{ $employee->date_of_leaving->format('d M Y') }}</p>
                                    </div>
                                    <div class="col-md-8">
                                        <small class="text-muted">Reason</small>
                                        <p class="mb-0">{{ $employee->leaving_reason ?? '-' }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Statutory Tab --}}
                <div class="tab-pane fade" id="statutory">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            <h6 class="mb-3">Identity Documents</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <small class="text-muted">PAN Number</small>
                                    <p class="mb-0">{{ $employee->pan_number ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Aadhar Number</small>
                                    <p class="mb-0">{{ $employee->aadhar_number ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Voter ID</small>
                                    <p class="mb-0">{{ $employee->voter_id ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Passport</small>
                                    <p class="mb-0">{{ $employee->passport_number ?? '-' }}
                                        @if($employee->passport_expiry)
                                            <small class="text-muted">(Exp: {{ $employee->passport_expiry->format('d M Y') }})</small>
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Driving License</small>
                                    <p class="mb-0">{{ $employee->driving_license ?? '-' }}
                                        @if($employee->dl_expiry)
                                            <small class="text-muted">(Exp: {{ $employee->dl_expiry->format('d M Y') }})</small>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-3">Statutory Compliance</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Provident Fund (PF)</strong>
                                            <span class="badge bg-{{ $employee->pf_applicable ? 'success' : 'secondary' }}">
                                                {{ $employee->pf_applicable ? 'Applicable' : 'Not Applicable' }}
                                            </span>
                                        </div>
                                        @if($employee->pf_applicable)
                                            <p class="mb-1"><small class="text-muted">UAN/PF No:</small> {{ $employee->pf_number ?? '-' }}</p>
                                            <p class="mb-0"><small class="text-muted">Join Date:</small> {{ $employee->pf_join_date?->format('d M Y') ?? '-' }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>ESI</strong>
                                            <span class="badge bg-{{ $employee->esi_applicable ? 'success' : 'secondary' }}">
                                                {{ $employee->esi_applicable ? 'Applicable' : 'Not Applicable' }}
                                            </span>
                                        </div>
                                        @if($employee->esi_applicable)
                                            <p class="mb-1"><small class="text-muted">ESI No:</small> {{ $employee->esi_number ?? '-' }}</p>
                                            <p class="mb-0"><small class="text-muted">Join Date:</small> {{ $employee->esi_join_date?->format('d M Y') ?? '-' }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Professional Tax</strong>
                                            <span class="badge bg-{{ $employee->pt_applicable ? 'success' : 'secondary' }}">
                                                {{ $employee->pt_applicable ? 'Yes' : 'No' }}
                                            </span>
                                        </div>
                                        @if($employee->pt_applicable)
                                            <p class="mb-0"><small class="text-muted">State:</small> {{ $employee->pt_state ?? '-' }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>TDS</strong>
                                            <span class="badge bg-{{ $employee->tds_applicable ? 'success' : 'secondary' }}">
                                                {{ $employee->tds_applicable ? 'Yes' : 'No' }}
                                            </span>
                                        </div>
                                        <p class="mb-0"><small class="text-muted">Tax Regime:</small> {{ ucfirst($employee->tax_regime ?? 'new') }}</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Gratuity</strong>
                                            <span class="badge bg-{{ $employee->gratuity_applicable ? 'success' : 'secondary' }}">
                                                {{ $employee->gratuity_applicable ? 'Yes' : 'No' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bank Tab --}}
                <div class="tab-pane fade" id="bank">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            <h6 class="mb-3">Primary Bank Account</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <small class="text-muted">Bank Name</small>
                                    <p class="mb-0">{{ $employee->bank_name ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Branch</small>
                                    <p class="mb-0">{{ $employee->bank_branch ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Account Type</small>
                                    <p class="mb-0">{{ ucfirst($employee->bank_account_type ?? '-') }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Account Number</small>
                                    <p class="mb-0">{{ $employee->bank_account_number ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">IFSC Code</small>
                                    <p class="mb-0">{{ $employee->bank_ifsc ?? '-' }}</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Payment Mode</small>
                                    <p class="mb-0">{{ ucfirst(str_replace('_', ' ', $employee->payment_mode ?? '-')) }}</p>
                                </div>
                            </div>

                            @if($employee->bankAccounts && $employee->bankAccounts->count() > 0)
                                <hr>
                                <h6 class="mb-3">Additional Bank Accounts</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Bank</th>
                                                <th>Account No</th>
                                                <th>IFSC</th>
                                                <th>Type</th>
                                                <th>Primary</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($employee->bankAccounts as $account)
                                                <tr>
                                                    <td>{{ $account->bank_name }}</td>
                                                    <td>{{ $account->account_number }}</td>
                                                    <td>{{ $account->ifsc_code }}</td>
                                                    <td>{{ ucfirst($account->account_type) }}</td>
                                                    <td>
                                                        @if($account->is_primary)
                                                            <span class="badge bg-success">Yes</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Documents Tab --}}
                <div class="tab-pane fade" id="documents">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            @if($employee->documents && $employee->documents->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Document Type</th>
                                                <th>Document Number</th>
                                                <th>Expiry Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($employee->documents as $doc)
                                                <tr>
                                                    <td>{{ $doc->document_type }}</td>
                                                    <td>{{ $doc->document_number ?? '-' }}</td>
                                                    <td>
                                                        @if($doc->expiry_date)
                                                            {{ $doc->expiry_date->format('d M Y') }}
                                                            @if($doc->expiry_date < now())
                                                                <span class="badge bg-danger">Expired</span>
                                                            @elseif($doc->expiry_date < now()->addDays(30))
                                                                <span class="badge bg-warning text-dark">Expiring Soon</span>
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ $doc->is_verified ? 'success' : 'warning' }}">
                                                            {{ $doc->is_verified ? 'Verified' : 'Pending' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($doc->file_path)
                                                            <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-download"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-file-earmark fs-1 d-block mb-2"></i>
                                    No documents uploaded
                                </div>
                            @endif

                            <hr>
                            @can('hr.employee.update')
                                <a href="{{ route('hr.employees.documents.index', $employee) }}" class="btn btn-primary">
                                    <i class="bi bi-upload me-1"></i> Manage Documents
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
