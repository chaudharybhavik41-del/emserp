@extends('layouts.erp')

@section('title', isset($location) ? 'Edit Work Location' : 'Add Work Location')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($location) ? 'Edit Work Location' : 'Add Work Location' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.work-locations.index') }}">Work Locations</a></li>
                <li class="breadcrumb-item active">{{ isset($location) ? 'Edit' : 'Add' }}</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST"
                          action="{{ isset($location) ? route('hr.work-locations.update', $location) : route('hr.work-locations.store') }}">
                        @csrf
                        @if(isset($location))
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror"
                                       id="code" name="code"
                                       value="{{ old('code', $location->code ?? '') }}"
                                       maxlength="20" required style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name"
                                       value="{{ old('name', $location->name ?? '') }}"
                                       maxlength="150" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Address</h6>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control @error('address') is-invalid @enderror"
                                      id="address" name="address" rows="3"
                                      placeholder="Optional">{{ old('address', $location->address ?? '') }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control @error('city') is-invalid @enderror"
                                       id="city" name="city"
                                       value="{{ old('city', $location->city ?? '') }}"
                                       maxlength="100">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control @error('state') is-invalid @enderror"
                                       id="state" name="state"
                                       value="{{ old('state', $location->state ?? '') }}"
                                       maxlength="100">
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control @error('pincode') is-invalid @enderror"
                                       id="pincode" name="pincode"
                                       value="{{ old('pincode', $location->pincode ?? '') }}"
                                       maxlength="10">
                                @error('pincode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3">Geofencing (DPR / Attendance)</h6>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" class="form-control @error('latitude') is-invalid @enderror"
                                       id="latitude" name="latitude"
                                       value="{{ old('latitude', $location->latitude ?? '') }}"
                                       step="0.000001" min="-90" max="90">
                                @error('latitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" class="form-control @error('longitude') is-invalid @enderror"
                                       id="longitude" name="longitude"
                                       value="{{ old('longitude', $location->longitude ?? '') }}"
                                       step="0.000001" min="-180" max="180">
                                @error('longitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="geofence_radius_meters" class="form-label">Geofence Radius (m)</label>
                                <input type="number" class="form-control @error('geofence_radius_meters') is-invalid @enderror"
                                       id="geofence_radius_meters" name="geofence_radius_meters"
                                       value="{{ old('geofence_radius_meters', $location->geofence_radius_meters ?? 250) }}"
                                       min="50" max="5000">
                                @error('geofence_radius_meters')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="getCurrentLocation">
                                <i class="bi bi-geo-alt me-1"></i> Get Current Location
                            </button>
                            <small class="text-muted ms-2">Click to auto-fill coordinates from your browser</small>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_active"
                                   name="is_active" value="1"
                                   {{ old('is_active', $location->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                {{ isset($location) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('hr.work-locations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('getCurrentLocation').addEventListener('click', function() {
    if (navigator.geolocation) {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Getting location...';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(6);

                document.getElementById('getCurrentLocation').disabled = false;
                document.getElementById('getCurrentLocation').innerHTML = '<i class="bi bi-geo-alt me-1"></i> Get Current Location';
            },
            function(error) {
                alert('Error getting location: ' + error.message);
                document.getElementById('getCurrentLocation').disabled = false;
                document.getElementById('getCurrentLocation').innerHTML = '<i class="bi bi-geo-alt me-1"></i> Get Current Location';
            }
        );
    } else {
        alert('Geolocation is not supported by your browser');
    }
});
</script>
@endpush
@endsection
