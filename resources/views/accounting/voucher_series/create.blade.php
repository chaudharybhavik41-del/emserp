@extends('layouts.erp')

@section('title', 'Create Voucher Series')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Create Voucher Series</h1>
        <a href="{{ route('accounting.voucher-series.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.voucher-series.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Key <span class="text-danger">*</span></label>
                        <input type="text" name="key" class="form-control @error('key') is-invalid @enderror"
                               value="{{ old('key') }}" list="known_series_keys" placeholder="purchase, sales, payment..." required>
                        <datalist id="known_series_keys">
                            @foreach($knownSeries as $k => $p)
                                <option value="{{ $k }}">
                            @endforeach
                        </datalist>
                        @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Internal key used by posting services. Keep it stable.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Purchase Bill">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Prefix <span class="text-danger">*</span></label>
                        <input type="text" name="prefix" class="form-control @error('prefix') is-invalid @enderror" value="{{ old('prefix') }}" placeholder="PB" required>
                        @error('prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Must be unique within the company across all series.</div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="use_financial_year" value="1" id="use_financial_year"
                                   {{ old('use_financial_year') ? 'checked' : '' }}>
                            <label class="form-check-label" for="use_financial_year">Use Financial Year in number</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Separator <span class="text-danger">*</span></label>
                        <input type="text" name="separator" class="form-control @error('separator') is-invalid @enderror" value="{{ old('separator', '/') }}" required>
                        @error('separator')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Pad Length <span class="text-danger">*</span></label>
                        <input type="number" name="pad_length" class="form-control @error('pad_length') is-invalid @enderror" value="{{ old('pad_length', 4) }}" min="2" max="12" required>
                        @error('pad_length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                   {{ old('is_active', 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('accounting.voucher-series.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
