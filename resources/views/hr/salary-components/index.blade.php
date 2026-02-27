@extends('layouts.erp')

@section('title', 'Salary Components')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Salary Components</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Salary Components</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.salary-components.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Component
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
                    <select name="component_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="earning" {{ request('component_type') == 'earning' ? 'selected' : '' }}>Earning</option>
                        <option value="deduction" {{ request('component_type') == 'deduction' ? 'selected' : '' }}>Deduction</option>
                        <option value="employer_contribution" {{ request('component_type') == 'employer_contribution' ? 'selected' : '' }}>Employer Contribution</option>
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
                    <a href="{{ route('hr.salary-components.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                            <th class="text-center">Type</th>
                            <th class="text-center">Calculation</th>
                            <th class="text-center">Taxable</th>
                            <th class="text-center">Affects</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($components as $component)
                            <tr>
                                <td><code>{{ $component->code }}</code></td>
                                <td>
                                    {{ $component->name }}
                                    @if($component->short_name)
                                        <small class="text-muted">({{ $component->short_name }})</small>
                                    @endif
                                    @if($component->is_statutory)
                                        <span class="badge bg-warning text-dark ms-1">Statutory</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($component->component_type == 'earning')
                                        <span class="badge bg-success">Earning</span>
                                    @elseif($component->component_type == 'deduction')
                                        <span class="badge bg-danger">Deduction</span>
                                    @else
                                        <span class="badge bg-info">Employer</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(in_array($component->calculation_type, ['percent_of_basic', 'percent_of_gross', 'percent_of_ctc']))
                                        <span class="badge bg-light text-dark">
                                            {{ number_format($component->percentage, 2) }}% 
                                            <small>of {{ str_replace('percent_of_', '', $component->calculation_type) }}</small>
                                        </span>
                                    @elseif($component->calculation_type == 'fixed')
                                        <span class="badge bg-light text-dark">₹{{ number_format($component->default_value, 0) }}</span>
                                    @elseif($component->calculation_type == 'formula')
                                        <span class="badge bg-secondary">Formula</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $component->calculation_type)) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($component->is_taxable)
                                        <span class="badge bg-warning text-dark">Yes</span>
                                    @else
                                        <span class="badge bg-light text-dark">No</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($component->affects_pf)
                                        <span class="badge bg-primary me-1" title="Affects PF">PF</span>
                                    @endif
                                    @if($component->affects_esi)
                                        <span class="badge bg-info me-1" title="Affects ESI">ESI</span>
                                    @endif
                                    @if($component->affects_gratuity)
                                        <span class="badge bg-secondary" title="Affects Gratuity">G</span>
                                    @endif
                                    @if(!$component->affects_pf && !$component->affects_esi && !$component->affects_gratuity)
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($component->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.salary-components.edit', $component) }}" 
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if(!$component->is_statutory)
                                        <form method="POST" action="{{ route('hr.salary-components.destroy', $component) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this component?')">
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
                                <td colspan="8" class="text-center text-muted py-4">No salary components found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($components->hasPages())
            <div class="card-footer">
                {{ $components->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
