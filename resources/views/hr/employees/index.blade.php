@extends('layouts.erp')

@section('title', 'HR Employees')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Employee Management</h1>
            <small class="text-muted">Manage employee master records</small>
        </div>
        @can('hr.employee.create')
            <a href="{{ route('hr.employees.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Employee
            </a>
        @endcan
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Total Employees</h6>
                            <h3 class="card-title mb-0">{{ number_format($stats['total']) }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Active</h6>
                            <h3 class="card-title mb-0">{{ number_format($stats['active']) }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-person-check"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1">On Probation</h6>
                            <h3 class="card-title mb-0">{{ number_format($stats['on_probation']) }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Left This Month</h6>
                            <h3 class="card-title mb-0">{{ number_format($stats['left_this_month']) }}</h3>
                        </div>
                        <div class="fs-1 opacity-50"><i class="bi bi-person-dash"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('hr.employees.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Code, Name, Mobile, Email..." value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        @foreach($statuses as $value => $label)
                            @if($value != 'active')
                                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Designation</label>
                    <select name="designation_id" class="form-select">
                        <option value="">All Designations</option>
                        @foreach($designations as $desig)
                            <option value="{{ $desig->id }}" {{ request('designation_id') == $desig->id ? 'selected' : '' }}>{{ $desig->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Employment Type</label>
                    <select name="employment_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach($employmentTypes as $value => $label)
                            <option value="{{ $value }}" {{ request('employment_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Employees Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">Photo</th>
                            <th>Emp. Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Mobile</th>
                            <th>Date of Joining</th>
                            <th>Status</th>
                            <th style="width: 120px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            <tr>
                                <td>
                                    @if($employee->photo_path)
                                        <img src="{{ Storage::url($employee->photo_path) }}" class="rounded-circle" width="40" height="40" alt="">
                                    @else
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($employee->first_name, 0, 1)) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('hr.employees.show', $employee) }}" class="fw-medium text-decoration-none">
                                        {{ $employee->employee_code }}
                                    </a>
                                </td>
                                <td>
                                    <div>{{ $employee->full_name }}</div>
                                    @if($employee->official_email)
                                        <small class="text-muted">{{ $employee->official_email }}</small>
                                    @endif
                                </td>
                                <td>{{ $employee->department?->name ?? '-' }}</td>
                                <td>{{ $employee->designation?->name ?? '-' }}</td>
                                <td>{{ $employee->personal_mobile ?? '-' }}</td>
                                <td>{{ $employee->date_of_joining?->format('d M Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $employee->status->color() }}">
                                        {{ $employee->status->label() }}
                                    </span>
                                    @if($employee->is_on_probation)
                                        <span class="badge bg-warning text-dark">Probation</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('hr.employee.update')
                                            <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="{{ route('hr.employees.salary.show', $employee) }}"><i class="bi bi-currency-rupee me-2"></i>Salary Details</a></li>
                                                <li><a class="dropdown-item" href="{{ route('hr.employees.leave-balance', $employee) }}"><i class="bi bi-calendar-check me-2"></i>Leave Balance</a></li>
                                                <li><a class="dropdown-item" href="{{ route('hr.employees.id-card', $employee) }}"><i class="bi bi-person-badge me-2"></i>ID Card</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                @if(!$employee->confirmation_date && $employee->status->value === 'active')
                                                    <li>
                                                        <form action="{{ route('hr.employees.confirm', $employee) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-success" onclick="return confirm('Confirm this employee?')">
                                                                <i class="bi bi-check-circle me-2"></i>Confirm Employee
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                @if($employee->status->value === 'active')
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#separationModal{{ $employee->id }}">
                                                            <i class="bi bi-person-x me-2"></i>Process Separation
                                                        </a>
                                                    </li>
                                                @endif
                                                @can('hr.employee.delete')
                                                    <li>
                                                        <form action="{{ route('hr.employees.destroy', $employee) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this employee?')">
                                                                <i class="bi bi-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endcan
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            {{-- Separation Modal --}}
                            @if($employee->status->value === 'active')
                            <div class="modal fade" id="separationModal{{ $employee->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('hr.employees.separation', $employee) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Process Separation - {{ $employee->full_name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Last Working Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="date_of_leaving" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Separation Type <span class="text-danger">*</span></label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="resigned">Resigned</option>
                                                        <option value="terminated">Terminated</option>
                                                        <option value="absconded">Absconded</option>
                                                        <option value="retired">Retired</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                                                    <textarea name="leaving_reason" class="form-control" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Process Separation</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                    No employees found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($employees->hasPages())
            <div class="card-footer">
                {{ $employees->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
