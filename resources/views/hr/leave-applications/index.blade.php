@extends('layouts.erp')

@section('title', 'Leave Applications')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Leave Applications</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Leave Applications</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.leave-applications.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> New Application
        </a>
    </div>

    @include('partials.flash')

    <div class="card">
        <div class="card-header bg-light py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search employee..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="leave_type_id" class="form-select form-select-sm">
                        <option value="">All Leave Types</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" {{ request('leave_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" name="from_date" class="form-control form-control-sm" 
                           value="{{ request('from_date') }}" placeholder="From">
                </div>
                <div class="col-auto">
                    <input type="date" name="to_date" class="form-control form-control-sm" 
                           value="{{ request('to_date') }}" placeholder="To">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('hr.leave-applications.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th class="text-center">From</th>
                            <th class="text-center">To</th>
                            <th class="text-center">Days</th>
                            <th>Reason</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $app)
                            @php
                                $statusValue = $app->status instanceof \BackedEnum
                                    ? $app->status->value
                                    : (string) $app->status;
                                $statusLabel = $app->status instanceof \App\Enums\Hr\LeaveStatus
                                    ? $app->status->label()
                                    : ucfirst(str_replace('_', ' ', $statusValue));
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" 
                                             style="width: 32px; height: 32px; font-size: 12px;">
                                            {{ strtoupper(substr($app->employee->first_name, 0, 1) . substr($app->employee->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div>{{ $app->employee->full_name }}</div>
                                            <small class="text-muted">{{ $app->employee->employee_code }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($app->leaveType->color)
                                        <span class="badge" style="background-color: {{ $app->leaveType->color }};">
                                            {{ $app->leaveType->name }}
                                        </span>
                                    @else
                                        {{ $app->leaveType->name }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    {{ $app->from_date->format('d M Y') }}
                                    <small class="d-block text-muted">{{ ucfirst(str_replace('_', ' ', $app->from_session)) }}</small>
                                </td>
                                <td class="text-center">
                                    {{ $app->to_date->format('d M Y') }}
                                    <small class="d-block text-muted">{{ ucfirst(str_replace('_', ' ', $app->to_session)) }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $app->total_days }}</span>
                                </td>
                                <td>{{ Str::limit($app->reason, 30) }}</td>
                                <td class="text-center">
                                    @switch($statusValue)
                                        @case('pending')
                                            <span class="badge bg-warning text-dark">Pending Approval</span>
                                            @break
                                        @case('approved')
                                            <span class="badge bg-success">Approved</span>
                                            @break
                                        @case('rejected')
                                            <span class="badge bg-danger">Rejected</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge bg-secondary">Cancelled</span>
                                            @break
                                        @default
                                            <span class="badge bg-light text-dark">{{ $statusLabel }}</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.leave-applications.show', $app) }}" 
                                       class="btn btn-sm btn-outline-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if(in_array($statusValue, ['pending', 'draft'], true))
                                        <a href="{{ route('hr.leave-applications.edit', $app) }}" 
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if($statusValue === 'pending')
                                        <form method="POST" action="{{ route('hr.leave-applications.approve', $app) }}" 
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" 
                                                    title="Approve" onclick="return confirm('Approve this leave?')">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No leave applications found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($applications->hasPages())
            <div class="card-footer">
                {{ $applications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
