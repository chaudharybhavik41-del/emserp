@extends('layouts.erp')

@section('title', 'Work Locations')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Work Locations</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Work Locations</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.work-locations.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Location
        </a>
    </div>

    @include('partials.flash')

    <div class="card">
        <div class="card-header bg-light py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('hr.work-locations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>City</th>
                            <th>State</th>
                            <th class="text-center">Geofence</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                            <tr>
                                <td><code>{{ $location->code }}</code></td>
                                <td>{{ $location->name }}</td>
                                <td>{{ $location->city ?? '-' }}</td>
                                <td>{{ $location->state ?? '-' }}</td>
                                <td class="text-center">
                                    @if($location->latitude && $location->longitude)
                                        <span class="badge bg-success" title="Lat: {{ $location->latitude }}, Lng: {{ $location->longitude }}">
                                            <i class="bi bi-geo-alt-fill"></i> {{ $location->geofence_radius_meters ?? 100 }}m
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">Not Set</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $location->employees_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($location->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.work-locations.edit', $location) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($location->employees_count == 0)
                                        <form method="POST" action="{{ route('hr.work-locations.destroy', $location) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this location?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No work locations found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($locations->hasPages())
            <div class="card-footer">
                {{ $locations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
