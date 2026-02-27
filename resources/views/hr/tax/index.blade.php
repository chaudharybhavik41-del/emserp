@extends('layouts.erp')

@section('title', 'Tax Dashboard')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Tax Dashboard (FY {{ $fy }})</h4>
        <a href="{{ route('hr.tax.declarations.create') }}" class="btn btn-primary btn-sm">New Declaration</a>
    </div>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Total</small><h4 class="mb-0">{{ $summary['total'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Submitted</small><h4 class="mb-0">{{ $summary['submitted'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Verified</small><h4 class="mb-0">{{ $summary['verified'] }}</h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Declared Amount</small><h4 class="mb-0">₹{{ number_format($summary['declared_amount'], 2) }}</h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Employee</th><th>FY</th><th>Regime</th><th>Declared</th><th>Verified</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($declarations as $declaration)
                            <tr>
                                <td>{{ $declaration->employee?->full_name }}</td>
                                <td>{{ $declaration->financial_year }}</td>
                                <td>{{ strtoupper($declaration->tax_regime) }}</td>
                                <td>₹{{ number_format($declaration->total_declared, 2) }}</td>
                                <td>₹{{ number_format($declaration->total_verified, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($declaration->status) }}</span></td>
                                <td class="text-end"><a href="{{ route('hr.tax.declarations.show', $declaration) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No declarations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($declarations->hasPages())<div class="card-footer">{{ $declarations->links() }}</div>@endif
    </div>
</div>
@endsection
