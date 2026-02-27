@extends('layouts.erp')

@section('title', 'Attendance Policies')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Attendance Policies</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Attendance Policies</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.attendance-policies.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Policy
        </a>
    </div>

    @include('partials.flash')

    <div class="card">
        <div class="card-header bg-light py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('hr.attendance-policies.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th class="text-center">Late Grace</th>
                            <th class="text-center">Min Full Day</th>
                            <th class="text-center">OT</th>
                            <th class="text-center">Weekend</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($policies as $policy)
                            <tr>
                                <td><code>{{ $policy->code }}</code></td>
                                <td>{{ $policy->name }}</td>
                                <td class="text-center">{{ $policy->late_coming_grace_minutes ?? 0 }} min</td>
                                <td class="text-center">{{ $policy->min_working_hours_full_day ?? '-' }} hrs</td>
                                <td class="text-center">
                                    @if($policy->overtime_applicable)
                                        <span class="badge bg-success">{{ $policy->overtime_rate_multiplier ?? 1 }}x</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <small>{{ ucwords(str_replace('_', ' ', $policy->weekend_policy ?? 'Not Set')) }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $policy->employees_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($policy->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.attendance-policies.edit', $policy) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($policy->employees_count == 0)
                                        <form method="POST" action="{{ route('hr.attendance-policies.destroy', $policy) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this policy?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No attendance policies found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($policies->hasPages())
            <div class="card-footer">
                {{ $policies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
