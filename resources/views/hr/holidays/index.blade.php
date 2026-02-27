@extends('layouts.erp')

@section('title', 'Holiday Calendar')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Holiday Calendar - {{ $year }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Holidays</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('hr.holiday-calendars.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add Holiday
            </a>
        </div>
    </div>

    @include('partials.flash')

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Total Holidays</div>
                            <div class="fs-4 fw-bold">{{ $stats['total'] ?? 0 }}</div>
                        </div>
                        <i class="bi bi-calendar-heart fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Mandatory</div>
                            <div class="fs-4 fw-bold">{{ $stats['mandatory'] ?? 0 }}</div>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-white-50">Optional</div>
                            <div class="fs-4 fw-bold">{{ $stats['optional'] ?? 0 }}</div>
                        </div>
                        <i class="bi bi-question-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Copy to Next Year -->
    <div class="mb-3">
        <form method="POST" action="{{ route('hr.holiday-calendars.copy-to-next-year') }}" class="d-inline">
            @csrf
            <input type="hidden" name="from_year" value="{{ $year }}">
            <button type="submit" class="btn btn-outline-secondary btn-sm" 
                    onclick="return confirm('Copy all {{ $year }} holidays to {{ $year + 1 }}?')">
                <i class="bi bi-copy me-1"></i> Copy to {{ $year + 1 }}
            </button>
        </form>
    </div>

    <!-- Holidays by Month -->
    @forelse($holidays ?? [] as $month => $monthHolidays)
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0">{{ $month }} ({{ $monthHolidays->count() }} holidays)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Date</th>
                                <th>Holiday Name</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Optional</th>
                                <th>Description</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($monthHolidays as $holiday)
                                <tr>
                                    <td>
                                        <strong>{{ $holiday->holiday_date->format('d M') }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $holiday->holiday_date->format('l') }}</small>
                                    </td>
                                    <td>{{ $holiday->name }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $holiday->type_color }}">
                                            {{ $holiday->type_label }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($holiday->is_optional)
                                            <span class="badge bg-secondary">Optional</span>
                                        @else
                                            <span class="badge bg-primary">Mandatory</span>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($holiday->description, 50) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('hr.holiday-calendars.edit', $holiday) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('hr.holiday-calendars.destroy', $holiday) }}" 
                                              class="d-inline" onsubmit="return confirm('Delete this holiday?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                <p class="text-muted mt-3">No holidays found for {{ $year }}</p>
                <a href="{{ route('hr.holiday-calendars.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add First Holiday
                </a>
            </div>
        </div>
    @endforelse
</div>
@endsection
