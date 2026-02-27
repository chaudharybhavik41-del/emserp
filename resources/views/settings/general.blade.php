
@extends('layouts.erp')

@section('title', 'General Settings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">General Settings</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('settings.general.update') }}">
            @csrf

            <div class="mb-3">
                <label for="app_name" class="form-label">Application Name <span class="text-danger">*</span></label>
                <input type="text"
                       id="app_name"
                       name="app_name"
                       class="form-control @error('app_name') is-invalid @enderror"
                       value="{{ old('app_name', $data['app_name']) }}"
                       required>
                @error('app_name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="date_format" class="form-label">Date Format <span class="text-danger">*</span></label>
                <input type="text"
                       id="date_format"
                       name="date_format"
                       class="form-control @error('date_format') is-invalid @enderror"
                       value="{{ old('date_format', $data['date_format']) }}"
                       placeholder="e.g. d-m-Y">
                @error('date_format')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="default_company_id" class="form-label">Default Company</label>
                <select id="default_company_id"
                        name="default_company_id"
                        class="form-select @error('default_company_id') is-invalid @enderror">
                    <option value="">-- Select --</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            {{ (string) old('default_company_id', $data['default_company_id']) === (string) $company->id ? 'selected' : '' }}>
                            {{ $company->code }} - {{ $company->name }}
                        </option>
                    @endforeach
                </select>
                @error('default_company_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>


            <hr class="my-4">

            <h5 class="h6">Engineering Formulas</h5>
            <p class="text-muted small">
                These settings control auto KPI calculations in BOM (area m², cutting meters). You can override per BOM item as needed.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="plate_area_mode" class="form-label">Plate Area Formula</label>
                    <select id="plate_area_mode"
                            name="plate_area_mode"
                            class="form-select @error('plate_area_mode') is-invalid @enderror">
                        @php
                            $pam = old('plate_area_mode', $data['plate_area_mode'] ?? 'two_side');
                        @endphp
                        <option value="two_side" @selected($pam === 'two_side')>Two sides (2 × W × L)</option>
                        <option value="two_side_plus_edges" @selected($pam === 'two_side_plus_edges')>Two sides + edges (2 × W × L + Perimeter × Thickness)</option>
                        <option value="one_side" @selected($pam === 'one_side')>One side (W × L)</option>
                        <option value="factor" @selected($pam === 'factor')>Factor × (W × L)</option>
                    </select>
                    @error('plate_area_mode')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Used for steel plate items (Unit Area in m²).</div>
                </div>

                <div class="col-md-3">
                    <label for="plate_area_factor" class="form-label">Plate Area Factor</label>
                    <input type="number"
                           step="0.001"
                           min="0"
                           id="plate_area_factor"
                           name="plate_area_factor"
                           class="form-control @error('plate_area_factor') is-invalid @enderror"
                           value="{{ old('plate_area_factor', $data['plate_area_factor'] ?? 2.0) }}">
                    @error('plate_area_factor')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Only used when Plate Area Formula = <strong>Factor</strong>.</div>
                </div>

                <div class="col-md-3">
                    <label for="plate_cut_factor" class="form-label">Plate Cutting Factor</label>
                    <input type="number"
                           step="0.001"
                           min="0"
                           id="plate_cut_factor"
                           name="plate_cut_factor"
                           class="form-control @error('plate_cut_factor') is-invalid @enderror"
                           value="{{ old('plate_cut_factor', $data['plate_cut_factor'] ?? 1.0) }}">
                    @error('plate_cut_factor')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Cut meters = Perimeter × Factor (used for steel plate items).</div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
