@extends('layouts.erp')

@section('title', 'Attendance Detail')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Attendance Detail</h4>
    <div class="card"><div class="card-body">
        <p><strong>Employee:</strong> {{ $attendance->employee?->full_name ?? '-' }}</p>
        <p><strong>Date:</strong> {{ $attendance->attendance_date?->format('d M Y') ?? '-' }}</p>
        <p><strong>Status:</strong> {{ optional($attendance->status)->value ?? $attendance->status }}</p>
        <p><strong>In/Out:</strong> {{ $attendance->first_in?->format('h:i A') }} - {{ $attendance->last_out?->format('h:i A') }}</p>
        <a href="{{ route('hr.attendance.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div></div>
</div>
@endsection
