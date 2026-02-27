@extends('layouts.erp')

@section('title', 'Edit TDS Certificate')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">Edit TDS Certificate</h4>
            <div class="small text-muted">Update certificate details while preserving voucher-level TDS traceability.</div>
        </div>
        <a href="{{ route('accounting.reports.tds-certificates') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            <div class="fw-semibold small mb-1">Please correct the following:</div>
            <ul class="mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Direction</div>
            <div class="fw-semibold">{{ ucfirst($certificate->direction) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Party</div>
            <div class="fw-semibold">{{ optional($certificate->partyAccount)->name ?? '-' }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">Voucher</div>
            <div class="fw-semibold">
                @if($certificate->voucher)
                    <a href="{{ route('accounting.vouchers.show', $certificate->voucher->id) }}" class="text-decoration-none">
                        {{ $certificate->voucher->voucher_no }}
                    </a>
                    <div class="small text-muted">{{ $certificate->voucher->voucher_date?->format('d-m-Y') }}</div>
                @else
                    -
                @endif
            </div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">TDS Amount</div>
            <div class="fw-semibold">{{ number_format((float) $certificate->tds_amount, 2) }}</div>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.reports.tds-certificates.update', $certificate->id) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label class="form-label">TDS Section</label>
                    <select name="tds_section" class="form-select @error('tds_section') is-invalid @enderror">
                        <option value="">-- Select --</option>
                        @foreach($tdsSections as $sec)
                            <option value="{{ $sec->code }}" {{ old('tds_section', $certificate->tds_section) == $sec->code ? 'selected' : '' }}>
                                {{ $sec->code }} - {{ $sec->name }} ({{ number_format((float) $sec->default_rate, 4) }}%)
                            </option>
                        @endforeach
                    </select>
                    @error('tds_section')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label">TDS Rate %</label>
                    <input type="number" step="0.0001" name="tds_rate" class="form-control @error('tds_rate') is-invalid @enderror" value="{{ old('tds_rate', $certificate->tds_rate) }}">
                    @error('tds_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">TDS Amount</label>
                    <input type="number" step="0.01" name="tds_amount" class="form-control @error('tds_amount') is-invalid @enderror" value="{{ old('tds_amount', $certificate->tds_amount) }}" required>
                    @error('tds_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label">Certificate Date</label>
                    <input type="date" name="certificate_date" class="form-control @error('certificate_date') is-invalid @enderror" value="{{ old('certificate_date', optional($certificate->certificate_date)->format('Y-m-d')) }}">
                    @error('certificate_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Certificate No</label>
                    <input type="text" name="certificate_no" class="form-control @error('certificate_no') is-invalid @enderror" value="{{ old('certificate_no', $certificate->certificate_no) }}" maxlength="100">
                    @error('certificate_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" class="form-control @error('remarks') is-invalid @enderror" value="{{ old('remarks', $certificate->remarks) }}" maxlength="2000">
                    @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('accounting.reports.tds-certificates') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
