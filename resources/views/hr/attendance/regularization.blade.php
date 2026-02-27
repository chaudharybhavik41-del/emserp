@extends('layouts.erp')

@section('title', 'Attendance Regularization')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Attendance Regularization</h4>
    @include('partials.flash')
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Request No</th><th>Employee</th><th>Date</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($regularizations ?? [] as $regularization)
                    <tr>
                        <td>{{ $regularization->request_number }}</td>
                        <td>{{ $regularization->employee?->full_name }}</td>
                        <td>{{ $regularization->attendance_date?->format('d M Y') }}</td>
                        <td>{{ ucfirst($regularization->status) }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('hr.attendance.regularization.approve', $regularization) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('hr.attendance.regularization.reject', $regularization) }}" class="d-inline">@csrf<button class="btn btn-sm btn-danger">Reject</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No regularization requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
