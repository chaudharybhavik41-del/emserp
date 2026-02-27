@extends('layouts.erp')

@section('title', $title)

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ $title }}</h4>
        <a href="{{ route('hr.settings.' . $type . '-slabs.create') }}" class="btn btn-primary btn-sm">Add Slab</a>
    </div>

    @include('partials.flash')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        @if($type === 'pf')
                            <tr><th>Effective From</th><th>Wage Ceiling</th><th>Employee %</th><th>Employer PF %</th><th>Status</th><th class="text-end">Action</th></tr>
                        @elseif($type === 'esi')
                            <tr><th>Effective From</th><th>Wage Ceiling</th><th>Employee %</th><th>Employer %</th><th>Status</th><th class="text-end">Action</th></tr>
                        @elseif($type === 'pt')
                            <tr><th>State</th><th>Range</th><th>Tax</th><th>Frequency</th><th>Status</th><th class="text-end">Action</th></tr>
                        @elseif($type === 'tds')
                            <tr><th>FY</th><th>Regime</th><th>Income Range</th><th>Tax %</th><th>Status</th><th class="text-end">Action</th></tr>
                        @else
                            <tr><th>State</th><th>Employee</th><th>Employer</th><th>Frequency</th><th>Status</th><th class="text-end">Action</th></tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse($slabs as $slab)
                            <tr>
                                @if($type === 'pf')
                                    <td>{{ $slab->effective_from?->format('d M Y') }}</td><td>₹{{ number_format($slab->wage_ceiling, 2) }}</td><td>{{ number_format($slab->employee_contribution_rate, 2) }}</td><td>{{ number_format($slab->employer_pf_rate, 2) }}</td>
                                @elseif($type === 'esi')
                                    <td>{{ $slab->effective_from?->format('d M Y') }}</td><td>₹{{ number_format($slab->wage_ceiling, 2) }}</td><td>{{ number_format($slab->employee_rate, 2) }}</td><td>{{ number_format($slab->employer_rate, 2) }}</td>
                                @elseif($type === 'pt')
                                    <td>{{ $slab->state_name }} ({{ $slab->state_code }})</td><td>₹{{ number_format($slab->salary_from, 2) }} - ₹{{ number_format($slab->salary_to, 2) }}</td><td>₹{{ number_format($slab->tax_amount, 2) }}</td><td>{{ ucfirst($slab->frequency) }}</td>
                                @elseif($type === 'tds')
                                    <td>{{ $slab->financial_year }}</td><td>{{ strtoupper($slab->regime) }}</td><td>₹{{ number_format($slab->income_from, 2) }} - ₹{{ number_format($slab->income_to, 2) }}</td><td>{{ number_format($slab->tax_percent, 2) }}</td>
                                @else
                                    <td>{{ $slab->state_name }} ({{ $slab->state_code }})</td><td>₹{{ number_format($slab->employee_contribution, 2) }}</td><td>₹{{ number_format($slab->employer_contribution, 2) }}</td><td>{{ ucfirst(str_replace('_', ' ', $slab->frequency)) }}</td>
                                @endif
                                <td>{!! $slab->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                                <td class="text-end">
                                    <a href="{{ route('hr.settings.' . $type . '-slabs.edit', $slab) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('hr.settings.' . $type . '-slabs.destroy', $slab) }}" class="d-inline" onsubmit="return confirm('Delete slab?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No slabs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($slabs->hasPages())<div class="card-footer">{{ $slabs->links() }}</div>@endif
    </div>
</div>
@endsection
