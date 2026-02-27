@extends('layouts.erp')

@section('title', 'My Approvals')

@section('page_header')
    <div>
        <div class="h5 mb-0">My Approvals</div>
        <div class="small text-body-secondary">
            Approval requests assigned to you (or your role).
        </div>
    </div>
@endsection

@section('content')
    <form method="GET" action="{{ route('my-approvals.index') }}" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label mb-1">Module / Sub-module</label>
                <input type="text"
                       name="module"
                       value="{{ request('module') }}"
                       class="form-control form-control-sm"
                       placeholder="e.g. purchase.indents">
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Pending & In Progress</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1" type="submit">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('my-approvals.index') }}">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="small text-body-secondary">
            Showing <strong>{{ $approvals->count() }}</strong> of <strong>{{ $approvals->total() }}</strong> record(s)
        </div>

        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-bell me-1"></i> Notifications
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Module</th>
                <th>Action</th>
                <th>Requested By</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Current Step</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($approvals as $approval)
                @php
                    $status = $approval->status ?? 'pending';
                    $badge = match ($status) {
                        'approved' => 'bg-success',
                        'rejected' => 'bg-danger',
                        'in_progress' => 'bg-info text-dark',
                        'pending' => 'bg-warning text-dark',
                        default => 'bg-secondary',
                    };
                @endphp
                <tr>
                    <td>{{ $approval->id }}</td>
                    <td>
                        <div class="fw-semibold">{{ $approval->module }}</div>
                        @if($approval->sub_module)
                            <div class="small text-body-secondary">{{ $approval->sub_module }}</div>
                        @endif
                    </td>
                    <td>{{ $approval->action }}</td>
                    <td>{{ $approval->requester->name ?? 'System' }}</td>
                    <td>
                        <span class="badge {{ $badge }}">
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </span>
                    </td>
                    <td>{{ optional($approval->requested_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $approval->current_step ?? '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('my-approvals.show', $approval) }}" class="btn btn-sm btn-outline-primary">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No approvals found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $approvals->links() }}
    </div>
@endsection