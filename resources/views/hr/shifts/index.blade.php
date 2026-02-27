@extends('layouts.erp')

@section('title', 'Shifts')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Shifts</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Shifts</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.shifts.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Shift
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
                    <a href="{{ route('hr.shifts.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                            <th class="text-center">Timing</th>
                            <th class="text-center">Working Hours</th>
                            <th class="text-center">Grace Period</th>
                            <th class="text-center">OT</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shifts as $shift)
                            <tr>
                                <td><code>{{ $shift->code }}</code></td>
                                <td>
                                    {{ $shift->name }}
                                    @if($shift->is_night_shift)
                                        <span class="badge bg-dark ms-1">Night</span>
                                    @endif
                                    @if($shift->is_flexible)
                                        <span class="badge bg-info ms-1">Flexible</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    {{ \Carbon\Carbon::parse($shift->start_time)->format('h:i A') }} - 
                                    {{ \Carbon\Carbon::parse($shift->end_time)->format('h:i A') }}
                                    @if($shift->spans_next_day)
                                        <small class="text-muted">(+1)</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $shift->working_hours }} hrs</td>
                                <td class="text-center">{{ $shift->grace_period_minutes ?? 0 }} min</td>
                                <td class="text-center">
                                    @if($shift->ot_applicable)
                                        <span class="badge bg-success">Yes</span>
                                        <small class="text-muted">{{ $shift->ot_rate_multiplier ?? 1 }}x</small>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $shift->employees_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($shift->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.shifts.show', $shift) }}" 
                                       class="btn btn-sm btn-outline-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('hr.shifts.edit', $shift) }}" 
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('hr.shifts.duplicate', $shift) }}" 
                                          class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Duplicate">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                    </form>
                                    @if($shift->employees_count == 0)
                                        <form method="POST" action="{{ route('hr.shifts.destroy', $shift) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this shift?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No shifts found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($shifts->hasPages())
            <div class="card-footer">
                {{ $shifts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
