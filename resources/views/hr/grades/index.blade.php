@extends('layouts.erp')

@section('title', 'Grades')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Grades</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Grades</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.grades.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Grade
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
                    <a href="{{ route('hr.grades.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 80px;">Level</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Basic Range</th>
                            <th>Gross Range</th>
                            <th class="text-center">Probation</th>
                            <th class="text-center">Notice</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grades as $grade)
                            <tr>
                                <td class="text-center">
                                    <span class="badge bg-primary fs-6">{{ $grade->level }}</span>
                                </td>
                                <td><code>{{ $grade->code }}</code></td>
                                <td>{{ $grade->name }}</td>
                                <td>
                                    @if($grade->min_basic || $grade->max_basic)
                                        ₹{{ number_format($grade->min_basic ?? 0) }} - ₹{{ number_format($grade->max_basic ?? 0) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($grade->min_gross || $grade->max_gross)
                                        ₹{{ number_format($grade->min_gross ?? 0) }} - ₹{{ number_format($grade->max_gross ?? 0) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $grade->probation_months ?? 0 }} mo</td>
                                <td class="text-center">{{ $grade->notice_period_days ?? 0 }} days</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $grade->employees_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($grade->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.grades.edit', $grade) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($grade->employees_count == 0 && (!isset($grade->designations_count) || $grade->designations_count == 0))
                                        <form method="POST" action="{{ route('hr.grades.destroy', $grade) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this grade?')">
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
                                <td colspan="10" class="text-center text-muted py-4">No grades found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($grades->hasPages())
            <div class="card-footer">
                {{ $grades->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
