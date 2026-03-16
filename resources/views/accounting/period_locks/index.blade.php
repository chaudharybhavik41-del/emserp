@extends('layouts.erp')

@section('title', 'Accounting Period Locks')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Accounting Period Locks</h1>
            <div class="text-muted small">Use active locks to block voucher dates and posted-date corrections inside closed periods.</div>
        </div>
        <div class="small text-muted">
            Company #{{ $companyId }}
            @if(is_numeric($maxBackdateDays))
                · Max back-date window: {{ (int) $maxBackdateDays }} day(s)
            @endif
            @if(is_numeric($maxFutureDays))
                · Max future-date window: {{ (int) $maxFutureDays }} day(s)
            @endif
            @if($enforceCurrentFinancialYear)
                · FY guard enabled
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Quick Close</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-1">Previous Month</div>
                        <div class="small text-muted mb-3">{{ $previousMonth['start']->format('d-m-Y') }} to {{ $previousMonth['end']->format('d-m-Y') }}</div>
                        <form method="POST" action="{{ route('accounting.period-locks.quick-store') }}">
                            @csrf
                            <input type="hidden" name="quick_type" value="previous_month">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Close {{ $previousMonth['label'] }}</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-1">Previous Financial Year</div>
                        <div class="small text-muted mb-3">FY {{ $previousFy['label'] }} · {{ $previousFy['start']->format('d-m-Y') }} to {{ $previousFy['end']->format('d-m-Y') }}</div>
                        <form method="POST" action="{{ route('accounting.period-locks.quick-store') }}">
                            @csrf
                            <input type="hidden" name="quick_type" value="previous_financial_year">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Close FY {{ $previousFy['label'] }}</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-1">Close Through Today</div>
                        <div class="small text-muted mb-3">FY {{ $currentFy['label'] }} · {{ $currentFy['start']->format('d-m-Y') }} to {{ now()->format('d-m-Y') }}</div>
                        <form method="POST" action="{{ route('accounting.period-locks.quick-store') }}">
                            @csrf
                            <input type="hidden" name="quick_type" value="current_financial_year_to_date">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Close Through Today</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2 fw-semibold">Add Period Lock</div>
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.period-locks.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ old('from_date') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ old('to_date') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" value="{{ old('title') }}" placeholder="FY close / audit lock">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm d-block">Status</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">Active immediately</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label form-label-sm">Reason</label>
                    <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Optional note shown in validation messages.">{{ old('reason') }}</textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">Save Lock</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 fw-semibold">Existing Locks</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 14%">Period</th>
                        <th style="width: 18%">Title</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th style="width: 16%" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($locks as $lock)
                        <tr>
                            <td class="small">{{ optional($lock->from_date)->format('d-m-Y') }} to {{ optional($lock->to_date)->format('d-m-Y') }}</td>
                            <td class="small">{{ $lock->title ?: '-' }}</td>
                            <td class="small">
                                @if($lock->is_active)
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactive</span>
                                @endif
                            </td>
                            <td class="small">{{ $lock->reason ?: '-' }}</td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('accounting.period-locks.toggle', $lock) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                        {{ $lock->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('accounting.period-locks.destroy', $lock) }}" class="d-inline" onsubmit="return confirm('Delete this period lock?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center small text-muted py-3">No accounting period locks defined.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
