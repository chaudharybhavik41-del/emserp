@extends('layouts.erp')

@section('title', 'Edit Voucher Series')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Edit Voucher Series</h1>
            <div class="small text-muted">Key: {{ $voucherSeries->key }}</div>
        </div>
        <a href="{{ route('accounting.voucher-series.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="small text-muted">Next Voucher No (Preview)</div>
                    <div class="fw-semibold">{{ $preview }}</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">FY Code (default)</div>
                    <div class="fw-semibold">{{ $fyCodeDefault }}</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Max Used Seq (this FY)</div>
                    <div class="fw-semibold">{{ $maxUsed }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.voucher-series.update', $voucherSeries) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $voucherSeries->name) }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Prefix <span class="text-danger">*</span></label>
                        <input type="text" name="prefix" class="form-control @error('prefix') is-invalid @enderror" value="{{ old('prefix', $voucherSeries->prefix) }}" required>
                        @error('prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Must be unique within the company across all series.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Separator <span class="text-danger">*</span></label>
                        <input type="text" name="separator" class="form-control @error('separator') is-invalid @enderror" value="{{ old('separator', $voucherSeries->separator) }}" required>
                        @error('separator')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Pad Length <span class="text-danger">*</span></label>
                        <input type="number" name="pad_length" class="form-control @error('pad_length') is-invalid @enderror" value="{{ old('pad_length', $voucherSeries->pad_length) }}" min="2" max="12" required>
                        @error('pad_length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="use_financial_year" value="1" id="use_financial_year"
                                   {{ old('use_financial_year', $voucherSeries->use_financial_year) ? 'checked' : '' }}>
                            <label class="form-check-label" for="use_financial_year">Use Financial Year</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   {{ old('is_active', $voucherSeries->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4" />

                <h6 class="mb-2">Counter (Optional)</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">FY Code</label>
                        <input type="text" name="counter_fy_code" class="form-control @error('counter_fy_code') is-invalid @enderror"
                               value="{{ old('counter_fy_code', $fyCodeDefault) }}"
                               {{ $voucherSeries->use_financial_year ? '' : 'readonly' }}>
                        @error('counter_fy_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">For non-FY series this is fixed to NA.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Next Number</label>
                        <input type="number" name="next_number" class="form-control @error('next_number') is-invalid @enderror"
                               value="{{ old('next_number', optional($counter)->next_number) }}" min="1">
                        @error('next_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Must be greater than Max Used Seq for that FY.</div>
                    </div>

                    <div class="col-md-4">
                        <div class="alert alert-warning py-2 small mb-0 mt-4">
                            Changing counters can affect numbering. Use only when required.
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('accounting.voucher-series.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.voucher-series.destroy', $voucherSeries) }}" onsubmit="return confirm('Delete this voucher series?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Delete Series</button>
                <div class="small text-muted mt-2">Deletion is blocked if vouchers already exist for this prefix.</div>
            </form>
        </div>
    </div>
</div>
@endsection
