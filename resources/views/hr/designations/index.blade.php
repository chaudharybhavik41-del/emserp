@extends('layouts.erp')

@section('title', 'Designations')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Designations</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Designations</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.designations.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Designation
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
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments ?? [] as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
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
                    <a href="{{ route('hr.designations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                            <th>Department</th>
                            <th>Grade</th>
                            <th class="text-center">Level</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($designations as $designation)
                            <tr>
                                <td><code>{{ $designation->code }}</code></td>
                                <td>
                                    {{ $designation->name }}
                                    @if($designation->is_managerial)
                                        <span class="badge bg-primary ms-1">Manager</span>
                                    @endif
                                    @if($designation->is_supervisory)
                                        <span class="badge bg-info ms-1">Supervisor</span>
                                    @endif
                                </td>
                                <td>{{ $designation->department->name ?? '-' }}</td>
                                <td>{{ $designation->grade->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $designation->sort_order ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $designation->employees_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($designation->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.designations.edit', $designation) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($designation->employees_count == 0)
                                        <form method="POST" action="{{ route('hr.designations.destroy', $designation) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this designation?')">
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
                                <td colspan="8" class="text-center text-muted py-4">No designations found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($designations->hasPages())
            <div class="card-footer">
                {{ $designations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
