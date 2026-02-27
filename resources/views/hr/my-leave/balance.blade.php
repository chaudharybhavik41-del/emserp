@extends('layouts.erp')

@section('title', 'My Leave Balance')

@section('content')
<div class="container-fluid py-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">My Leave Balance</h4>
            <div class="text-muted small">Current year leave entitlement and availability.</div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('hr.my.leave.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to My Leave
            </a>
            <a href="{{ route('hr.my.leave.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Apply Leave
            </a>
        </div>
    </div>

    @include('partials.flash')

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Year</label>
                    <input type="number" min="2000" max="2100" name="year" value="{{ $year }}" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-4">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Load
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Leave Type</th>
                            <th class="text-end" style="width: 120px;">Entitled</th>
                            <th class="text-end" style="width: 120px;">Used</th>
                            <th class="text-end" style="width: 120px;">Pending</th>
                            <th class="text-end" style="width: 140px;">Available</th>
                            <th style="width: 170px;">Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                /** @var \App\Models\Hr\HrLeaveType $t */
                                $t = $row['leave_type'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $t->name }}</div>
                                    @if(!empty($t->code))
                                        <div class="text-muted small">{{ $t->code }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ number_format((float)$row['entitled'], 1) }}</td>
                                <td class="text-end">{{ number_format((float)$row['used'], 1) }}</td>
                                <td class="text-end">{{ number_format((float)$row['pending'], 1) }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float)$row['available'], 1) }}</td>
                                <td class="small text-muted">
                                    {{ $row['has_balance_row'] ? 'Balance table' : 'Leave type default' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center p-4 text-muted">
                                    No leave types configured.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white text-muted small">
            Note: If leave balances for {{ $year }} are not yet processed/created, the system shows the leave type default entitlement.
        </div>
    </div>

</div>
@endsection
