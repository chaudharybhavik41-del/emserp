@extends('layouts.erp')

@section('title', 'Employee ID Card')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">ID Card</h4>
    <div class="card" style="max-width: 420px;">
        <div class="card-body text-center">
            @if($employee->photo_path)
                <img src="{{ Storage::url($employee->photo_path) }}" alt="Photo" class="rounded-circle mb-2" width="90" height="90">
            @endif
            <h5 class="mb-1">{{ $employee->full_name }}</h5>
            <p class="mb-1">{{ $employee->designation?->name ?? '-' }}</p>
            <p class="mb-1"><strong>{{ $employee->employee_code }}</strong></p>
            <p class="text-muted">{{ $employee->department?->name ?? '-' }}</p>
            <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>
</div>
@endsection
