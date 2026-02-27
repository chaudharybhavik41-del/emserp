@extends('layouts.erp')

@section('title', 'My Leave')

@section('content')
<div class="container-fluid py-3">

    @php
        $hasBalanceRoute = \Illuminate\Support\Facades\Route::has('hr.my.leave.balance');
        $hasCreateRoute  = \Illuminate\Support\Facades\Route::has('hr.my.leave.create');
        $hasIndexRoute   = \Illuminate\Support\Facades\Route::has('hr.my.leave.index');
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0">My Leave</h4>
            <div class="text-muted small">Apply leave and track your leave applications.</div>
        </div>

        <div class="d-flex gap-2">
            @if($hasBalanceRoute)
                <a href="{{ route('hr.my.leave.balance') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pie-chart me-1"></i> My Leave Balance
                </a>
            @endif

            @if($hasCreateRoute)
                <a href="{{ route('hr.my.leave.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Apply Leave
                </a>
            @endif
        </div>
    </div>

    @include('partials.flash')

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $v)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>

                    @if($hasIndexRoute)
                        <a href="{{ route('hr.my.leave.index') }}" class="btn btn-sm btn-outline-secondary ms-1">
                            Reset
                        </a>
                    @endif
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
                            <th style="width: 160px;">Applied</th>
                            <th>Leave Type</th>
                            <th style="width: 220px;">Dates</th>
                            <th style="width: 100px;" class="text-end">Days</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 140px;" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $app)
                            <tr>
                                <td class="text-muted small">
                                    {{ optional($app->created_at)->format('d M Y') ?? '-' }}
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        {{ $app->leaveType->name ?? '—' }}
                                    </div>
                                    @if(!empty($app->application_number))
                                        <div class="text-muted small">#{{ $app->application_number }}</div>
                                    @endif
                                </td>

                                <td class="small">
                                    <div><strong>From:</strong> {{ \Carbon\Carbon::parse($app->from_date)->format('d M Y') }}</div>
                                    <div><strong>To:</strong> {{ \Carbon\Carbon::parse($app->to_date)->format('d M Y') }}</div>
                                </td>

                                <td class="text-end fw-semibold">
                                    {{ number_format((float)($app->total_days ?? 0), 1) }}
                                </td>

                                <td>
                                    @php
                                        // status can be enum-cast (App\Enums\Hr\LeaveStatus) depending on model casts
                                        $statusObj = $app->status ?? 'pending';
                                        $st = $statusObj instanceof \BackedEnum ? $statusObj->value : (string) $statusObj;

                                        $badge = match($st) {
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'cancelled' => 'bg-secondary',
                                            default => 'bg-warning text-dark',
                                        };

                                        $label = ($statusObj instanceof \App\Enums\Hr\LeaveStatus && method_exists($statusObj, 'label'))
                                            ? $statusObj->label()
                                            : ucfirst($st);
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $label }}</span>
                                </td>

                                <td class="text-end">
                                    @if($st === 'pending' && \Illuminate\Support\Facades\Route::has('hr.my.leave.cancel'))
                                        <form method="POST" action="{{ route('hr.my.leave.cancel', $app) }}" class="d-inline">
                                            @csrf
                                            <button type="submit"
                                                    onclick="return confirm('Cancel this leave application?')"
                                                    class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center p-4 text-muted">
                                    No leave applications found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($applications, 'links'))
            <div class="card-footer bg-white">
                {{ $applications->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
