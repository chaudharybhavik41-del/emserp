@extends('layouts.erp')

@section('title', 'Holiday List')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Holiday List - {{ $year }}</h4>
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Name</th><th>Type</th><th>Optional</th></tr></thead>
            <tbody>
                @forelse($holidays as $holiday)
                    <tr>
                        <td>{{ $holiday->holiday_date?->format('d M Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td>{{ ucfirst($holiday->holiday_type) }}</td>
                        <td>{{ $holiday->is_optional ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No holidays found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
