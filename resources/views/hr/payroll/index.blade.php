@extends('layouts.erp')

@section('title', 'Payroll Management')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Payroll Management</h1>
            <small class="text-muted">Manage salary processing and payroll periods</small>
        </div>
        @can('hr.payroll.create')
            @if(Route::has('hr.payroll.create-period'))
            <a href="{{ route('hr.payroll.create-period') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create Period
            </a>
            @endif
        @endcan
    </div>

    {{-- Current Period Summary --}}
    @if($summary['current_period'] ?? null)
        @php $currentPeriod = $summary['current_period']; @endphp
        <div class="card bg-light mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <h6 class="text-muted mb-1">Current Period</h6>
                        <h5 class="mb-0">{{ $currentPeriod->name }}</h5>
                        <small>{{ $currentPeriod->period_start->format('d M') }} - {{ $currentPeriod->period_end->format('d M Y') }}</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <h6 class="text-muted mb-1">Status</h6>
                        <span class="badge bg-{{ $currentPeriod->status_color }} fs-6">{{ $currentPeriod->status_label }}</span>
                    </div>
                    <div class="col-md-2 text-center">
                        <h6 class="text-muted mb-1">Processed</h6>
                        <h4 class="mb-0">{{ $summary['total_processed'] ?? 0 }}</h4>
                    </div>
                    <div class="col-md-2 text-center">
                        <h6 class="text-muted mb-1">Total Net Pay</h6>
                        <h4 class="mb-0">₹{{ number_format($summary['total_net_pay'] ?? 0, 0) }}</h4>
                    </div>
                    <div class="col-md-3 text-end">
                        @if(Route::has('hr.payroll.period'))
                        <a href="{{ route('hr.payroll.period', $currentPeriod) }}" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i> View Details
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Processed</h6>
                            <h3 class="mb-0">{{ $summary['total_processed'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 text-primary opacity-50"><i class="bi bi-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Paid</h6>
                            <h3 class="mb-0">{{ $summary['total_paid'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 text-success opacity-50"><i class="bi bi-currency-rupee"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Net Pay</h6>
                            <h3 class="mb-0">₹{{ number_format($summary['total_net_pay'] ?? 0, 0) }}</h3>
                        </div>
                        <div class="fs-1 text-info opacity-50"><i class="bi bi-wallet2"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Periods</h6>
                            <h3 class="mb-0">{{ $periods->total() }}</h3>
                        </div>
                        <div class="fs-1 text-secondary opacity-50"><i class="bi bi-calendar3"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('hr.payroll.index') }}" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <select name="year" class="form-select form-select-sm">
                        @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}" {{ request('year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Payroll Periods Table --}}
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Payroll Periods</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th>Date Range</th>
                            <th class="text-center">Working Days</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $period->name }}</div>
                                    <small class="text-muted">{{ $period->period_code }}</small>
                                </td>
                                <td>
                                    {{ $period->period_start->format('d M') }} - {{ $period->period_end->format('d M Y') }}
                                </td>
                                <td class="text-center">{{ $period->working_days }}</td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $period->payrolls_count ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $period->status_color }}">{{ $period->status_label }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if(Route::has('hr.payroll.period'))
                                        <a href="{{ route('hr.payroll.period', $period) }}" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @endif
                                        @can('hr.payroll.process')
                                        @if(in_array($period->status, ['draft', 'attendance_locked']))
                                            @if(Route::has('hr.payroll.period.process'))
                                            <form action="{{ route('hr.payroll.period.process', $period) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success" onclick="return confirm('Process payroll for this period?')">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            </form>
                                            @endif
                                        @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No payroll periods found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($periods->hasPages())
        <div class="card-footer">
            {{ $periods->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
