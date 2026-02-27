@extends('layouts.erp')

@section('title', 'Leave Application')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Leave Application</h4>
    <div class="card"><div class="card-body">
        <p><strong>Employee:</strong> {{ $application->employee?->full_name }}</p>
        <p><strong>Leave Type:</strong> {{ $application->leaveType?->name }}</p>
        <p><strong>Period:</strong> {{ $application->from_date?->format('d M Y') }} - {{ $application->to_date?->format('d M Y') }}</p>
        <p><strong>Total Days:</strong> {{ $application->total_days }}</p>
        <p><strong>Status:</strong> {{ ucfirst(optional($application->status)->value ?? $application->status) }}</p>
        <p><strong>Reason:</strong> {{ $application->reason }}</p>
        <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div></div>
</div>
@endsection
