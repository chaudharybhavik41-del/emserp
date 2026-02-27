@extends('layouts.erp')

@section('title', 'Leave Year End Processing')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Leave Year End Processing</h4>
            <small class="text-muted">Carry forward eligible leave balances from one year to another.</small>
        </div>
        <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Leave
        </a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Process Carry Forward</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('hr.leave.year-end') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">From Year <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                name="from_year"
                                class="form-control @error('from_year') is-invalid @enderror"
                                value="{{ old('from_year', $fromYear ?? now()->subYear()->year) }}"
                                min="2000"
                                max="2100"
                                required
                            >
                            @error('from_year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">To Year <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                name="to_year"
                                class="form-control @error('to_year') is-invalid @enderror"
                                value="{{ old('to_year', $toYear ?? now()->year) }}"
                                min="2000"
                                max="2100"
                                required
                            >
                            @error('to_year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-warning small">
                            <div class="fw-semibold mb-1">Important</div>
                            <div>This action adds carry-forward into target year balances for eligible leave types.</div>
                            <div>Run once per year pair to avoid duplicate carry-forward.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-1"></i> Run Year End Processing
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Preview Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Source Year</div>
                                <div class="h5 mb-0">{{ $summary['from_year'] ?? ($fromYear ?? '-') }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Target Year</div>
                                <div class="h5 mb-0">{{ $summary['to_year'] ?? ($toYear ?? '-') }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Source Balances</div>
                                <div class="h5 mb-0">{{ $summary['source_balances'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Eligible Balances</div>
                                <div class="h5 mb-0">{{ $summary['eligible_balances'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Estimated Carry Days</div>
                                <div class="h5 mb-0">{{ number_format((float) ($summary['estimated_days'] ?? 0), 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
