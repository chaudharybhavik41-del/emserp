@extends('layouts.erp')

@section('title', 'OT Approval')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Overtime Approval</h4>
    @include('partials.flash')
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Employee</th><th>OT Hours</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($records ?? [] as $record)
                    <tr>
                        <td>{{ $record->attendance_date?->format('d M Y') }}</td>
                        <td>{{ $record->employee?->full_name }}</td>
                        <td>{{ $record->ot_hours }}</td>
                        <td>{{ $record->ot_status }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('hr.attendance.approve-ot', $record) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('hr.attendance.reject-ot', $record) }}" class="d-inline">@csrf<button class="btn btn-sm btn-danger">Reject</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No OT requests pending.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
