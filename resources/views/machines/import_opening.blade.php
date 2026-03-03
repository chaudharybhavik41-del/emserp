@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-upload"></i> Import Opening Machinery</h2>
        <a href="{{ route('machines.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Machines
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>CSV Format</strong></div>
        <div class="card-body">
            <p class="mb-2">Required columns:</p>
            <ul class="mb-3">
                <li><code>name</code></li>
                <li><code>opening_wdv</code></li>
            </ul>
            <p class="mb-2">Optional columns:</p>
            <div class="small text-muted">
                <code>asset_code</code>, <code>serial_no</code>, <code>make</code>, <code>model</code>,
                <code>location</code>, <code>opening_date</code>, <code>opening_cost</code>,
                <code>opening_accum_depr</code>, <code>purchase_date</code>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Upload</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('machines.import-opening.store') }}" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Cutover Date (default for blank opening_date)</label>
                    <input type="date" name="cutover_date" class="form-control @error('cutover_date') is-invalid @enderror"
                           value="{{ old('cutover_date', $defaultCutoverDate ?? '2026-01-01') }}">
                    @error('cutover_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv,text/plain"
                           class="form-control @error('csv_file') is-invalid @enderror" required>
                    @error('csv_file')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    @php
        $preview = $openingPreview ?? null;
        $existingVoucher = is_array($preview) ? ($preview['existing_voucher'] ?? null) : null;
    @endphp
    @if(is_array($preview))
    <div class="card mb-3">
        <div class="card-header"><strong>Opening FA JV Posting</strong></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-2 text-center">
                        <div class="small text-muted">Cutover Date</div>
                        <div class="fw-semibold">{{ $preview['cutover_date'] ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-2 text-center">
                        <div class="small text-muted">Eligible Assets</div>
                        <div class="fs-5 fw-semibold">{{ (int) ($preview['asset_count'] ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-2 text-center">
                        <div class="small text-muted">Total Opening WDV</div>
                        <div class="fs-5 fw-semibold">{{ number_format((float) ($preview['total_opening_wdv'] ?? 0), 2) }}</div>
                    </div>
                </div>
            </div>

            @if($existingVoucher)
                <div class="alert alert-success d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong>Opening FA JV already posted:</strong>
                        {{ $existingVoucher->voucher_no ?? ('#' . $existingVoucher->id) }}
                    </div>
                    @if(\Illuminate\Support\Facades\Route::has('accounting.vouchers.show'))
                        <a href="{{ route('accounting.vouchers.show', $existingVoucher) }}" class="btn btn-sm btn-outline-success">
                            Open Voucher
                        </a>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('machines.import-opening.post-fa-jv') }}" class="row g-2">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Cutover Date</label>
                    <input type="date" name="cutover_date"
                           class="form-control @error('cutover_date') is-invalid @enderror"
                           value="{{ old('cutover_date', $preview['cutover_date'] ?? ($defaultCutoverDate ?? '2026-01-01')) }}"
                           required>
                    @error('cutover_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8 d-flex align-items-end">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-journal-check"></i> Post Opening FA JV
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @php
        $summary = session('import_summary');
    @endphp
    @if(is_array($summary))
        <div class="card">
            <div class="card-header"><strong>Last Import Summary</strong></div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Created</div>
                            <div class="fs-4 fw-semibold text-success">{{ (int) ($summary['created'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Skipped</div>
                            <div class="fs-4 fw-semibold text-warning">{{ (int) ($summary['skipped'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Errors</div>
                            <div class="fs-4 fw-semibold text-danger">{{ count($summary['errors'] ?? []) }}</div>
                        </div>
                    </div>
                </div>

                @if(!empty($summary['errors']))
                    <div class="alert alert-warning mb-0">
                        <strong>Row errors:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach(array_slice($summary['errors'], 0, 20) as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                            @if(count($summary['errors']) > 20)
                                <li>... and {{ count($summary['errors']) - 20 }} more</li>
                            @endif
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
