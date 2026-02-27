@extends('layouts.erp')

@section('title', 'Import Attendance Punches')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Import Attendance Punches</h1>
            <small class="text-muted">Upload CSV/TXT punch logs and optionally process attendance.</small>
        </div>
        <a href="{{ route('hr.attendance.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Attendance
        </a>
    </div>

    @include('partials.flash')

    @if(session('import_errors') && is_array(session('import_errors')) && count(session('import_errors')) > 0)
        <div class="alert alert-warning">
            <div class="fw-semibold mb-2">Import warnings (showing up to 50)</div>
            <ul class="mb-0 ps-3">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Upload File</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('hr.attendance.import-punches') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label">CSV/TXT File <span class="text-danger">*</span></label>
                            <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".csv,.txt" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="default_date" class="form-label">Default Date (optional)</label>
                            <input type="date" id="default_date" name="default_date" class="form-control @error('default_date') is-invalid @enderror" value="{{ old('default_date') }}">
                            <small class="text-muted">Used when file rows contain only time (no date).</small>
                            @error('default_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="has_header" name="has_header" value="1" {{ old('has_header', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="has_header">
                                First row has header columns
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="auto_process" name="auto_process" value="1" {{ old('auto_process', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="auto_process">
                                Auto-process attendance after import
                            </label>
                        </div>

                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-upload me-1"></i> Import Punches
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Supported Columns</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3 small">
                        <li>Employee: <code>employee_code</code>, <code>biometric_id</code>, <code>hr_employee_id</code>, <code>card_number</code></li>
                        <li>Date-time: <code>punch_time</code> or <code>date</code> + <code>time</code></li>
                        <li>Optional: <code>punch_type</code>, <code>device_id</code>, <code>location_name</code>, <code>remarks</code></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent Imported/Recorded Punches</h6>
                    <span class="text-muted small">Latest 50</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date Time</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPunches as $punch)
                                    <tr>
                                        <td>{{ $punch->punch_time?->format('d M Y h:i:s A') }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $punch->employee?->employee_code }}</div>
                                            <small class="text-muted">{{ $punch->employee?->full_name }}</small>
                                        </td>
                                        <td><span class="badge bg-secondary">{{ strtoupper($punch->punch_type ?? 'unknown') }}</span></td>
                                        <td>{{ ucfirst($punch->source ?? '-') }}</td>
                                        <td>{{ $punch->device_id ?: '-' }}</td>
                                        <td>
                                            @if($punch->is_valid)
                                                <span class="badge bg-success">Valid</span>
                                            @else
                                                <span class="badge bg-danger">Invalid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No punch records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if(method_exists($recentPunches, 'links'))
                    <div class="card-footer">
                        {{ $recentPunches->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
