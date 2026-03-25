@extends('layouts.erp')

@section('title', $employee->full_name)

@section('content')
@php($activeSection = $activeSection ?? 'overview')

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $employee->full_name }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        @if($canManageEmployee ?? false)
                            <a href="{{ route('hr.employees.index') }}">Employees</a>
                        @else
                            <a href="{{ route('hr.my.workspace') }}">My HR</a>
                        @endif
                    </li>
                    <li class="breadcrumb-item active">{{ $employee->employee_code }}</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            @if($canManageEmployee ?? false)
                <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endif
            <a href="{{ route('hr.employees.id-card', $employee) }}" class="btn btn-outline-primary">
                <i class="bi bi-person-badge me-1"></i> ID Card
            </a>
            @if($canManageEmployee ?? false)
                <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            @endif
        </div>
    </div>

    @include('hr.employees.partials.hub-nav', ['employee' => $employee, 'activeSection' => $activeSection])

    <div class="row g-3">
        <div class="col-lg-4 col-xl-3">
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

                    <div class="text-start small">
                        <p class="mb-2"><strong>Emp. Code:</strong> {{ $employee->employee_code }}</p>
                        <p class="mb-2"><strong>Department:</strong> {{ $employee->department?->name ?? '-' }}</p>
                        <p class="mb-2"><strong>Joined:</strong> {{ $employee->date_of_joining?->format('d M Y') ?? '-' }}</p>
                        <p class="mb-2"><strong>Reporting To:</strong> {{ $employee->reportingManager?->full_name ?? '-' }}</p>
                        <p class="mb-0"><strong>Service:</strong> {{ $employee->service_years }} yrs {{ $employee->service_months % 12 }} months</p>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-6 col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Current Salary</div>
                            <div class="fw-semibold">{{ $employee->currentSalary ? '₹' . number_format((float) $employee->currentSalary->monthly_gross, 2) : '-' }}</div>
                            <div class="text-muted small">Monthly gross</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Attendance This Month</div>
                            <div class="fw-semibold">{{ number_format((float) ($hubStats['attendance_paid_days'] ?? 0), 1) }} days</div>
                            <div class="text-muted small">Paid days</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Leave Balance</div>
                            <div class="fw-semibold">{{ number_format((float) ($hubStats['leave_balance'] ?? 0), 1) }} days</div>
                            <div class="text-muted small">Current year</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Outstanding Recoveries</div>
                            <div class="fw-semibold">₹{{ number_format((float) (($hubStats['loan_outstanding'] ?? 0) + ($hubStats['advance_balance'] ?? 0)), 2) }}</div>
                            <div class="text-muted small">Loans + advances</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    @if($isOwnWorkspace ?? false)
                        <a href="{{ route('hr.my.leave.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-list-check me-1"></i> My Leave Requests
                        </a>
                        <a href="{{ route('hr.my.leave.create') }}" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Apply Leave
                        </a>
                        <a href="{{ route('hr.my.leave.balance') }}" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-pie-chart me-1"></i> My Leave Balance
                        </a>
                    @endif
                    <a href="{{ route('hr.employees.salary.show', $employee) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-currency-rupee me-1"></i> Salary Details
                    </a>
                    @if($canManageEmployee ?? false)
                        <a href="{{ route('hr.employees.leave-balance', $employee) }}" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-calendar-check me-1"></i> Leave Balance
                        </a>
                    @endif
                    <a href="{{ route('hr.employees.attendance', $employee) }}" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-calendar3 me-1"></i> Attendance
                    </a>
                    <a href="{{ route('hr.employees.payroll', $employee) }}" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i> Payroll History
                    </a>
                    @can('accounting.reports.view')
                        @if($employee->accountingLedger)
                            <a href="{{ route('accounting.reports.ledger', ['account_id' => $employee->accountingLedger->id, 'from_date' => $ledgerReportRange['start']->toDateString(), 'to_date' => $ledgerReportRange['end']->toDateString()]) }}" class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-journal-text me-1"></i> Employee Ledger
                            </a>
                        @endif
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
            @if($activeSection === 'overview')
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Current Net Salary</div>
                                <div class="h5 mb-0">{{ $employee->currentSalary ? '₹' . number_format((float) $employee->currentSalary->monthly_net, 2) : '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Latest Payroll</div>
                                <div class="h5 mb-0">₹{{ number_format((float) ($hubStats['latest_net_pay'] ?? 0), 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Active Loans</div>
                                <div class="h5 mb-0">₹{{ number_format((float) ($hubStats['loan_outstanding'] ?? 0), 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Salary Advances</div>
                                <div class="h5 mb-0">₹{{ number_format((float) ($hubStats['advance_balance'] ?? 0), 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Employee Overview</h6>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="employeeOverviewTabs" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#personal">Personal</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#employment">Employment</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#statutory">Statutory</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bank">Bank</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents">Documents</button></li>
                        </ul>

                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="personal">
                                <div class="row g-3">
                                    <div class="col-md-4"><small class="text-muted">Full Name</small><p class="mb-0 fw-medium">{{ $employee->full_name }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Father's Name</small><p class="mb-0">{{ $employee->father_name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Mother's Name</small><p class="mb-0">{{ $employee->mother_name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Date of Birth</small><p class="mb-0">{{ $employee->date_of_birth?->format('d M Y') ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Gender</small><p class="mb-0">{{ ucfirst($employee->gender ?? '-') }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Marital Status</small><p class="mb-0">{{ ucfirst($employee->marital_status ?? '-') }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Personal Email</small><p class="mb-0">{{ $employee->personal_email ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Official Email</small><p class="mb-0">{{ $employee->official_email ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Mobile</small><p class="mb-0">{{ $employee->personal_mobile ?? '-' }}</p></div>
                                </div>
                                <hr>
                                <h6 class="mb-2">Address</h6>
                                <p class="mb-1">{{ $employee->present_address ?? '-' }}</p>
                                <p class="mb-0">{{ trim(($employee->present_city ?? '') . ', ' . ($employee->present_state ?? '') . ' - ' . ($employee->present_pincode ?? '')) }}</p>
                            </div>

                            <div class="tab-pane fade" id="employment">
                                <div class="row g-3">
                                    <div class="col-md-4"><small class="text-muted">Employee Code</small><p class="mb-0 fw-medium">{{ $employee->employee_code }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Biometric ID</small><p class="mb-0">{{ $employee->biometric_id ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Card Number</small><p class="mb-0">{{ $employee->card_number ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Date of Joining</small><p class="mb-0">{{ $employee->date_of_joining?->format('d M Y') ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Confirmation Date</small><p class="mb-0">{{ $employee->confirmation_date?->format('d M Y') ?? ($employee->is_on_probation ? 'On Probation' : '-') }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Employment Type</small><p class="mb-0">{{ ucfirst($employee->employment_type ?? '-') }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Department</small><p class="mb-0">{{ $employee->department?->name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Designation</small><p class="mb-0">{{ $employee->designation?->name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Work Location</small><p class="mb-0">{{ $employee->workLocation?->name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Default Shift</small><p class="mb-0">{{ $employee->defaultShift?->name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Overtime Applicable</small><p class="mb-0">{{ $employee->overtime_applicable ? 'Yes' : 'No' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Salary Structure</small><p class="mb-0">{{ $employee->salaryStructure?->name ?? '-' }}</p></div>
                                </div>
                                @if($employee->date_of_leaving)
                                    <hr>
                                    <h6 class="text-danger mb-2">Separation</h6>
                                    <p class="mb-1"><strong>Date:</strong> {{ $employee->date_of_leaving->format('d M Y') }}</p>
                                    <p class="mb-0"><strong>Reason:</strong> {{ $employee->leaving_reason ?? '-' }}</p>
                                @endif
                            </div>

                            <div class="tab-pane fade" id="statutory">
                                <div class="row g-3">
                                    <div class="col-md-4"><small class="text-muted">PAN</small><p class="mb-0">{{ $employee->pan_number ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Aadhar</small><p class="mb-0">{{ $employee->aadhar_number ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Voter ID</small><p class="mb-0">{{ $employee->voter_id ?? '-' }}</p></div>
                                </div>
                                <hr>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between"><strong>PF</strong><span class="badge bg-{{ $employee->pf_applicable ? 'success' : 'secondary' }}">{{ $employee->pf_applicable ? 'Applicable' : 'N/A' }}</span></div>
                                            <div class="small mt-2">{{ $employee->pf_number ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between"><strong>ESI</strong><span class="badge bg-{{ $employee->esi_applicable ? 'success' : 'secondary' }}">{{ $employee->esi_applicable ? 'Applicable' : 'N/A' }}</span></div>
                                            <div class="small mt-2">{{ $employee->esi_number ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <div class="d-flex justify-content-between"><strong>Tax</strong><span class="badge bg-{{ $employee->tds_applicable ? 'success' : 'secondary' }}">{{ $employee->tds_applicable ? 'Applicable' : 'N/A' }}</span></div>
                                            <div class="small mt-2">{{ ucfirst($employee->tax_regime ?? 'new') }} regime</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="bank">
                                <div class="row g-3">
                                    <div class="col-md-4"><small class="text-muted">Bank Name</small><p class="mb-0">{{ $employee->bank_name ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">Account Number</small><p class="mb-0">{{ $employee->bank_account_number ?? '-' }}</p></div>
                                    <div class="col-md-4"><small class="text-muted">IFSC</small><p class="mb-0">{{ $employee->bank_ifsc ?? '-' }}</p></div>
                                </div>
                                @if($employee->bankAccounts->isNotEmpty())
                                    <hr>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Bank</th>
                                                    <th>Account No</th>
                                                    <th>IFSC</th>
                                                    <th>Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($employee->bankAccounts as $account)
                                                    <tr>
                                                        <td>{{ $account->bank_name }}</td>
                                                        <td>{{ $account->account_number }}</td>
                                                        <td>{{ $account->ifsc_code }}</td>
                                                        <td>{{ ucfirst($account->account_type) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade" id="documents">
                                @if($employee->documents->isNotEmpty())
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Document</th>
                                                    <th>Number</th>
                                                    <th>Expiry</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($employee->documents as $doc)
                                                    <tr>
                                                        <td>{{ $doc->document_type }}</td>
                                                        <td>{{ $doc->document_number ?? '-' }}</td>
                                                        <td>{{ $doc->expiry_date?->format('d M Y') ?? '-' }}</td>
                                                        <td><span class="badge bg-{{ $doc->is_verified ? 'success' : 'warning' }}">{{ $doc->is_verified ? 'Verified' : 'Pending' }}</span></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-muted">No documents uploaded.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h6 class="mb-0">Recent Attendance</h6></div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentAttendance as $attendance)
                                                <tr>
                                                    <td>{{ $attendance->attendance_date?->format('d M Y') }}</td>
                                                    <td><span class="badge bg-{{ $attendance->status->color() }}">{{ $attendance->status->shortCode() }}</span></td>
                                                    <td>{{ number_format((float) $attendance->working_hours, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="text-center text-muted py-3">No attendance found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><h6 class="mb-0">Recent Payrolls</h6></div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Status</th>
                                                <th>Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentPayrolls as $payroll)
                                                <tr>
                                                    <td>{{ $payroll->period?->name ?? $payroll->payroll_number }}</td>
                                                    <td><span class="badge bg-{{ $payroll->status->color() }}">{{ $payroll->status->label() }}</span></td>
                                                    <td>₹{{ number_format((float) $payroll->net_payable, 2) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="text-center text-muted py-3">No payroll found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($activeSection === 'attendance')
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Month</label>
                                <input type="month" name="month" value="{{ ($selectedMonth ?? now())->format('Y-m') }}" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Load</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Paid Days</div><div class="h5 mb-0">{{ number_format((float) ($attendanceSummary['paid_days'] ?? 0), 1) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Present</div><div class="h5 mb-0">{{ $attendanceSummary['present_days'] ?? 0 }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Absent</div><div class="h5 mb-0">{{ $attendanceSummary['absent_days'] ?? 0 }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Approved OT Hours</div><div class="h5 mb-0">{{ number_format((float) ($attendanceSummary['ot_hours'] ?? 0), 2) }}</div></div></div></div>
                </div>
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Attendance Records</h6></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Shift</th>
                                        <th>In</th>
                                        <th>Out</th>
                                        <th>Working Hours</th>
                                        <th>OT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($attendanceRows ?? collect()) as $attendance)
                                        <tr>
                                            <td>{{ $attendance->attendance_date?->format('d M Y') }}</td>
                                            <td><span class="badge bg-{{ $attendance->status->color() }}">{{ $attendance->status->label() }}</span></td>
                                            <td>{{ $attendance->shift?->name ?? '-' }}</td>
                                            <td>{{ $attendance->formatted_in_time ?? '-' }}</td>
                                            <td>{{ $attendance->formatted_out_time ?? '-' }}</td>
                                            <td>{{ number_format((float) $attendance->working_hours, 2) }}</td>
                                            <td>{{ number_format((float) $attendance->ot_hours_approved, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">No attendance records found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($activeSection === 'leave')
                <div class="row g-3 mb-3">
                    @forelse(($leaveBalances ?? collect()) as $balance)
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="text-muted small">{{ $balance->leaveType?->name ?? 'Leave Type' }}</div>
                                    <div class="h5 mb-1">{{ number_format((float) $balance->available_balance, 1) }} days</div>
                                    <div class="small text-muted">Used {{ number_format((float) $balance->used, 1) }} | Pending {{ number_format((float) $balance->pending, 1) }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><div class="alert alert-light border mb-0">No leave balances found for {{ now()->year }}.</div></div>
                    @endforelse
                </div>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Leave Applications</h6>
                        <div class="d-flex gap-2">
                            @if($isOwnWorkspace ?? false)
                                <a href="{{ route('hr.my.leave.balance') }}" class="btn btn-sm btn-outline-secondary">My Balance</a>
                                <a href="{{ route('hr.my.leave.create') }}" class="btn btn-sm btn-primary">Apply Leave</a>
                            @elseif($canManageEmployee ?? false)
                                <a href="{{ route('hr.leave.create', ['employee_id' => $employee->id]) }}" class="btn btn-sm btn-primary">Apply / Create</a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Period</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($leaveApplications ?? collect()) as $application)
                                        <tr>
                                            <td>{{ $application->leaveType?->name ?? '-' }}</td>
                                            <td>{{ $application->period_text }}</td>
                                            <td>{{ number_format((float) $application->total_days, 1) }}</td>
                                            <td><span class="badge bg-{{ $application->status->color() }}">{{ $application->status->label() }}</span></td>
                                            <td><a href="{{ route('hr.leave-applications.show', $application) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">No leave applications found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($activeSection === 'payroll')
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">YTD Gross</div><div class="h5 mb-0">₹{{ number_format((float) ($payrollYtd['gross'] ?? 0), 2) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">YTD Deductions</div><div class="h5 mb-0">₹{{ number_format((float) ($payrollYtd['deductions'] ?? 0), 2) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">YTD Net</div><div class="h5 mb-0">₹{{ number_format((float) ($payrollYtd['net_payable'] ?? 0), 2) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Paid Payrolls</div><div class="h5 mb-0">{{ $payrollYtd['paid_count'] ?? 0 }}</div></div></div></div>
                </div>
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Payroll History</h6></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Paid Days</th>
                                        <th>Gross</th>
                                        <th>Deductions</th>
                                        <th>Net</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($employeePayrolls ?? collect()) as $payroll)
                                        <tr>
                                            <td>{{ $payroll->period?->name ?? $payroll->payroll_number }}</td>
                                            <td>{{ number_format((float) $payroll->paid_days, 1) }}</td>
                                            <td>₹{{ number_format((float) $payroll->gross_salary, 2) }}</td>
                                            <td>₹{{ number_format((float) $payroll->total_deductions, 2) }}</td>
                                            <td>₹{{ number_format((float) $payroll->net_payable, 2) }}</td>
                                            <td><span class="badge bg-{{ $payroll->status->color() }}">{{ $payroll->status->label() }}</span></td>
                                            <td class="text-nowrap">
                                                <a href="{{ route('hr.payroll.show', $payroll) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                                <a href="{{ route('hr.payroll.payslip', $payroll) }}" class="btn btn-sm btn-outline-primary">Payslip</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">No payroll records found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($activeSection === 'loans-advances')
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Loan Outstanding</div><div class="h5 mb-0">₹{{ number_format((float) ($loanAdvanceSummary['loan_outstanding'] ?? 0), 2) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Loan Recovered</div><div class="h5 mb-0">₹{{ number_format((float) ($loanAdvanceSummary['loan_recovered'] ?? 0), 2) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Advance Balance</div><div class="h5 mb-0">₹{{ number_format((float) ($loanAdvanceSummary['advance_balance'] ?? 0), 2) }}</div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Advance Recovered</div><div class="h5 mb-0">₹{{ number_format((float) ($loanAdvanceSummary['advance_recovered'] ?? 0), 2) }}</div></div></div></div>
                </div>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Employee Loans</h6>
                        <a href="{{ route('hr.loans.employee-loans.create', ['hr_employee_id' => $employee->id]) }}" class="btn btn-sm btn-primary">New Loan</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Loan No</th>
                                        <th>Type</th>
                                        <th>Disbursed</th>
                                        <th>Outstanding</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($employeeLoans ?? collect()) as $loan)
                                        <tr>
                                            <td>{{ $loan->loan_number }}</td>
                                            <td>{{ $loan->loanType?->name ?? '-' }}</td>
                                            <td>₹{{ number_format((float) $loan->disbursed_amount, 2) }}</td>
                                            <td>₹{{ number_format((float) $loan->total_outstanding, 2) }}</td>
                                            <td><span class="badge bg-{{ in_array($loan->status, ['active', 'disbursed', 'recovering'], true) ? 'warning' : 'secondary' }}">{{ ucfirst((string) $loan->status) }}</span></td>
                                            <td><a href="{{ route('hr.loans.employee-loans.show', $loan) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">No loans found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Salary Advances</h6>
                        <a href="{{ route('hr.advances.salary-advances.create', ['hr_employee_id' => $employee->id]) }}" class="btn btn-sm btn-primary">New Advance</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Advance No</th>
                                        <th>Approved</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($employeeAdvances ?? collect()) as $advance)
                                        <tr>
                                            <td>{{ $advance->advance_number }}</td>
                                            <td>₹{{ number_format((float) $advance->approved_amount, 2) }}</td>
                                            <td>₹{{ number_format((float) $advance->balance_amount, 2) }}</td>
                                            <td><span class="badge bg-{{ in_array($advance->status, ['approved', 'disbursed', 'recovering'], true) ? 'warning' : 'secondary' }}">{{ ucfirst((string) $advance->status) }}</span></td>
                                            <td><a href="{{ route('hr.advances.salary-advances.show', $advance) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">No salary advances found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($activeSection === 'compliance')
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">PF</div><div class="h6 mb-1">{{ $employee->pf_applicable ? 'Applicable' : 'N/A' }}</div><div class="small text-muted">{{ $employee->pf_number ?? '-' }}</div></div></div></div>
                    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">ESI</div><div class="h6 mb-1">{{ $employee->esi_applicable ? 'Applicable' : 'N/A' }}</div><div class="small text-muted">{{ $employee->esi_number ?? '-' }}</div></div></div></div>
                    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">PT</div><div class="h6 mb-1">{{ $employee->pt_applicable ? 'Applicable' : 'N/A' }}</div><div class="small text-muted">{{ $employee->pt_state ?? '-' }}</div></div></div></div>
                    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Tax Regime</div><div class="h6 mb-1">{{ ucfirst($employee->tax_regime ?? 'new') }}</div><div class="small text-muted">{{ $employee->tds_applicable ? 'TDS active' : 'TDS not active' }}</div></div></div></div>
                </div>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Tax Declarations</h6>
                        <a href="{{ route('hr.tax.computation', $employee) }}" class="btn btn-sm btn-outline-primary">Tax Computation</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Financial Year</th>
                                        <th>Regime</th>
                                        <th>Status</th>
                                        <th>Declared</th>
                                        <th>Verified</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(($taxDeclarations ?? collect()) as $declaration)
                                        <tr>
                                            <td>{{ $declaration->financial_year }}</td>
                                            <td>{{ ucfirst($declaration->tax_regime) }}</td>
                                            <td>{{ ucfirst($declaration->status) }}</td>
                                            <td>₹{{ number_format((float) $declaration->total_declared, 2) }}</td>
                                            <td>₹{{ number_format((float) $declaration->total_verified, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">No tax declarations found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($activeSection === 'timeline')
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Employee Timeline</h6></div>
                    <div class="card-body">
                        @forelse(($timelineEvents ?? collect()) as $event)
                            <div class="d-flex gap-3 {{ !$loop->last ? 'pb-3 mb-3 border-bottom' : '' }}">
                                <div class="text-center" style="width: 110px;">
                                    <div class="fw-semibold">{{ \Illuminate\Support\Carbon::parse($event['date'])->format('d M Y') }}</div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold">{{ $event['title'] }}</div>
                                            <div class="text-muted small">{{ $event['meta'] }}</div>
                                        </div>
                                        <span class="badge bg-{{ $event['tone'] }}">{{ ucfirst($event['tone']) }}</span>
                                    </div>
                                    @if(!empty($event['action_url']))
                                        <a href="{{ $event['action_url'] }}" class="btn btn-sm btn-outline-secondary mt-2">Open</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">No timeline events found.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
