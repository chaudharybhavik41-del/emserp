@extends('layouts.erp')

@section('title', 'Attendance Bulk Entry')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h5 mb-0">Attendance Bulk Entry</h1>
            <small class="text-muted">Mark attendance for all employees for a single date</small>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('hr.attendance.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <form method="GET" class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div>
                <label class="form-label mb-1">Date</label>
                <input type="date" name="date" value="{{ $date }}" class="form-control" />
            </div>

            {{-- Optional filters (keep even if you don’t wire dropdowns now) --}}
            <div>
                <label class="form-label mb-1">Department ID (optional)</label>
                <input type="number" name="department_id" value="{{ $departmentId }}" class="form-control" />
            </div>

            <div>
                <label class="form-label mb-1">Shift ID (optional)</label>
                <input type="number" name="shift_id" value="{{ $shiftId }}" class="form-control" />
            </div>

            <div class="ms-auto">
                <button class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Load
                </button>
            </div>
        </div>
    </form>

    <form method="POST" class="card">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}"/>

        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="setAllStatus('present')">Mark All Present</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setAllStatus('absent')">Mark All Absent</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAllStatus('weekly_off')">Mark All Weekly Off</button>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="setAllStatus('holiday')">Mark All Holiday</button>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">Emp Code</th>
                        <th>Employee</th>
                        <th style="width: 200px;">Status</th>
                        <th style="width: 110px;">In</th>
                        <th style="width: 110px;">Out</th>
                        <th style="width: 110px;">OT Hours</th>
                        <th>Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($employees as $emp)
                        @php
                            $att = $existing[$emp->id] ?? null;
                            $selected = old("rows.{$emp->id}.status", $att?->status?->value ?? $att?->status ?? 'present');
                        @endphp

                        <tr>
                            <td class="text-muted">{{ $emp->employee_code }}</td>
                            <td>
                                <div class="fw-semibold">{{ $emp->full_name }}</div>
                                <div class="small text-muted">{{ $emp->department?->name ?? '' }}</div>
                            </td>

                            <td>
                                <select class="form-select form-select-sm status-select" name="rows[{{ $emp->id }}][status]">
                                    @foreach($statusOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $selected === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>

                            <td>
                                <input type="time" class="form-control form-control-sm"
                                       name="rows[{{ $emp->id }}][in_time]"
                                       value="{{ old("rows.{$emp->id}.in_time", $att?->in_time ? \Carbon\Carbon::parse($att->in_time)->format('H:i') : '') }}">
                            </td>

                            <td>
                                <input type="time" class="form-control form-control-sm"
                                       name="rows[{{ $emp->id }}][out_time]"
                                       value="{{ old("rows.{$emp->id}.out_time", $att?->out_time ? \Carbon\Carbon::parse($att->out_time)->format('H:i') : '') }}">
                            </td>

                            <td>
                                <input type="number" step="0.25" min="0" max="24" class="form-control form-control-sm"
                                       name="rows[{{ $emp->id }}][ot_hours]"
                                       value="{{ old("rows.{$emp->id}.ot_hours", $att?->ot_hours ?? 0) }}">
                            </td>

                            <td>
                                <input type="text" class="form-control form-control-sm"
                                       name="rows[{{ $emp->id }}][remarks]"
                                       value="{{ old("rows.{$emp->id}.remarks", $att?->remarks ?? '') }}">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No active employees found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> Save Attendance
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function setAllStatus(value) {
        document.querySelectorAll('.status-select').forEach(function (el) {
            el.value = value;
        });
    }
</script>
@endpush
@endsection
