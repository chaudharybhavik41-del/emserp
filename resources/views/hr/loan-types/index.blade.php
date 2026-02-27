@extends('layouts.erp')

@section('title', 'Loan Types')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Loan Types</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Loan Types</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.loan-types.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Loan Type
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
                    <a href="{{ route('hr.loan-types.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                            <th>Amount Range</th>
                            <th class="text-center">Interest</th>
                            <th class="text-center">Max Tenure</th>
                            <th class="text-center">Loans</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanTypes as $type)
                            <tr>
                                <td><code>{{ $type->code }}</code></td>
                                <td>
                                    {{ $type->name }}
                                    @if($type->requires_guarantor)
                                        <span class="badge bg-warning text-dark ms-1">Guarantor</span>
                                    @endif
                                </td>
                                <td>
                                    @if($type->min_amount || $type->max_amount)
                                        ₹{{ number_format($type->min_amount ?? 0) }} - ₹{{ number_format($type->max_amount ?? 0) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($type->interest_type === 'none')
                                        <span class="badge bg-success">Interest Free</span>
                                    @else
                                        {{ $type->interest_rate ?? 0 }}%
                                        <small class="text-muted d-block">{{ ucfirst($type->interest_type ?? 'simple') }}</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $type->max_tenure_months ?? '-' }} mo</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $type->loans_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($type->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.loan-types.edit', $type) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($type->loans_count == 0)
                                        <form method="POST" action="{{ route('hr.loan-types.destroy', $type) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this loan type?')">
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
                                <td colspan="8" class="text-center text-muted py-4">No loan types found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($loanTypes->hasPages())
            <div class="card-footer">
                {{ $loanTypes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
