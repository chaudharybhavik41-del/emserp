@extends('layouts.erp')

@section('title', 'Tax Declaration Details')

@section('content')
<div class="container-fluid py-3">
    @include('partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Tax Declaration</h4>
            <p class="text-muted mb-0">{{ $declaration->employee?->full_name }} | FY {{ $declaration->financial_year }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('hr.tax.declarations.edit', $declaration) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <a href="{{ route('hr.tax.computation', ['employee' => $declaration->hr_employee_id, 'financial_year' => $declaration->financial_year]) }}" class="btn btn-outline-secondary btn-sm">Computation</a>
            <a href="{{ route('hr.tax.declarations.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Declared</small><h5>₹{{ number_format($declaration->total_declared, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Verified</small><h5>₹{{ number_format($declaration->total_verified, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Exemption</small><h5>₹{{ number_format($declaration->total_exemption, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Status</small><h5><span class="badge bg-secondary">{{ ucfirst($declaration->status) }}</span></h5></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Section</th><th>Investment</th><th>Description</th><th>Declared</th><th>Verified</th></tr></thead>
                    <tbody>
                        @forelse($declaration->details as $detail)
                            <tr>
                                <td>{{ $detail->section_code }} - {{ $detail->section_name }}</td>
                                <td>{{ $detail->investment_type }}</td>
                                <td>{{ $detail->description ?: '-' }}</td>
                                <td>₹{{ number_format($detail->declared_amount, 2) }}</td>
                                <td>₹{{ number_format($detail->verified_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No detail rows.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <form method="POST" action="{{ route('hr.tax.declarations.submit', $declaration) }}">@csrf
                <button class="btn btn-success">Submit Declaration</button>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <form method="POST" action="{{ route('hr.tax.declarations.verify', $declaration) }}">@csrf
                @foreach($declaration->details as $i => $detail)
                    <input type="hidden" name="details[{{ $i }}][id]" value="{{ $detail->id }}">
                    <input type="hidden" name="details[{{ $i }}][verified_amount]" value="{{ $detail->declared_amount }}">
                @endforeach
                <button class="btn btn-primary">Verify Declaration</button>
            </form>
        </div>
    </div>
</div>
@endsection
