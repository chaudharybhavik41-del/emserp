@extends('layouts.erp')

@section('title', 'Tax Declarations')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Tax Declarations</h4>
        <a href="{{ route('hr.tax.declarations.create') }}" class="btn btn-primary btn-sm">Create Declaration</a>
    </div>

    @include('partials.flash')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Employee</th><th>FY</th><th>Regime</th><th>Declared</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($declarations as $declaration)
                            <tr>
                                <td>{{ $declaration->employee?->full_name }}</td>
                                <td>{{ $declaration->financial_year }}</td>
                                <td>{{ strtoupper($declaration->tax_regime) }}</td>
                                <td>₹{{ number_format($declaration->total_declared, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($declaration->status) }}</span></td>
                                <td class="text-end"><a href="{{ route('hr.tax.declarations.show', $declaration) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No declarations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($declarations->hasPages())<div class="card-footer">{{ $declarations->links() }}</div>@endif
    </div>
</div>
@endsection
