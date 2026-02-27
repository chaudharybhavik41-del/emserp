@extends('layouts.erp')

@section('title', 'Leave Types')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Leave Types</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Leave Types</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.leave-types.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Leave Type
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
                    <a href="{{ route('hr.leave-types.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                            <th class="text-center">Paid</th>
                            <th class="text-center">Encashable</th>
                            <th class="text-center">Carry Forward</th>
                            <th class="text-center">Half Day</th>
                            <th class="text-center">Document</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaveTypes as $type)
                            <tr>
                                <td>
                                    @if($type->color)
                                        <span class="badge" style="background-color: {{ $type->color }};">{{ $type->code }}</span>
                                    @else
                                        <code>{{ $type->code }}</code>
                                    @endif
                                </td>
                                <td>
                                    {{ $type->name }}
                                    @if($type->gender_specific)
                                        <span class="badge bg-info">{{ ucfirst($type->gender_specific) }} Only</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->is_paid)
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    @else
                                        <i class="bi bi-x-circle text-muted"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->is_encashable)
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    @else
                                        <i class="bi bi-x-circle text-muted"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->is_carry_forward)
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        @if($type->max_carry_forward_days)
                                            <small class="text-muted d-block">Max {{ $type->max_carry_forward_days }}d</small>
                                        @endif
                                    @else
                                        <i class="bi bi-x-circle text-muted"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->allow_half_day)
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    @else
                                        <i class="bi bi-x-circle text-muted"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->is_document_required)
                                        <i class="bi bi-file-earmark-text text-warning"></i>
                                        @if($type->document_required_after_days)
                                            <small class="text-muted d-block">>{{ $type->document_required_after_days }}d</small>
                                        @endif
                                    @else
                                        <i class="bi bi-x-circle text-muted"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.leave-types.edit', $type) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('hr.leave-types.destroy', $type) }}" 
                                          class="d-inline" onsubmit="return confirm('Delete this leave type?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No leave types found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($leaveTypes->hasPages())
            <div class="card-footer">
                {{ $leaveTypes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
