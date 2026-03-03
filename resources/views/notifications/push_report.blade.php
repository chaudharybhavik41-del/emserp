@extends('layouts.erp')

@section('title', 'Push Delivery Report')

@section('content')
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">PWA Push Delivery Report</h1>
        <small class="text-body-secondary">Subscription health and latest delivery status across users.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary">Back to Notifications</a>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Total</div><div class="fw-semibold">{{ $summary['total'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Active</div><div class="fw-semibold text-success">{{ $summary['active'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Disabled</div><div class="fw-semibold text-body-secondary">{{ $summary['disabled'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Sent (24h)</div><div class="fw-semibold text-primary">{{ $summary['sent_24h'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Failed (24h)</div><div class="fw-semibold text-danger">{{ $summary['failed_24h'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Stale</div><div class="fw-semibold text-warning">{{ $summary['stale'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Queue Pending</div><div class="fw-semibold text-info">{{ $summary['queue_pending'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card h-100"><div class="card-body py-2 px-3"><div class="small text-body-secondary">Queue Failed</div><div class="fw-semibold {{ $summary['queue_failed'] > 0 ? 'text-danger' : 'text-success' }}">{{ $summary['queue_failed'] }}</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header py-2 small fw-semibold">Maintenance Actions</div>
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <form method="POST" action="{{ route('notifications.push-report.prune') }}">
                    @csrf
                    <input type="hidden" name="mode" value="dry-run">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Prune Scan (Dry Run)</button>
                </form>
            </div>
            <div class="col-12 col-md-4">
                <form method="POST" action="{{ route('notifications.push-report.prune') }}">
                    @csrf
                    <input type="hidden" name="mode" value="apply">
                    <button type="submit" class="btn btn-sm btn-warning w-100">Apply Prune (Disable Stale)</button>
                </form>
            </div>
            <div class="col-12 col-md-4">
                <form method="POST" action="{{ route('notifications.push-report.prune') }}" onsubmit="return confirm('Hard delete stale active subscriptions and old disabled records?');">
                    @csrf
                    <input type="hidden" name="mode" value="hard-delete">
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Hard Delete Prune</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" value="{{ $search }}" placeholder="Name, email or endpoint">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="disabled" {{ $status === 'disabled' ? 'selected' : '' }}>Disabled</option>
                    <option value="sent" {{ $status === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed/Expired</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                <a href="{{ route('notifications.push-report') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>Endpoint</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Last Attempt</th>
                    <th>Last Success</th>
                    <th>Disabled</th>
                    <th>Error</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($subscriptions as $subscription)
                    @php
                        $state = $subscription->disabled_at ? 'Disabled' : 'Active';
                        $pushState = $subscription->last_push_status ?: 'n/a';
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $subscription->user?->name ?? 'Unknown User' }}</div>
                            <div class="small text-body-secondary">{{ $subscription->user?->email ?? '-' }}</div>
                        </td>
                        <td class="small">
                            <span class="text-body-secondary">{{ \Illuminate\Support\Str::limit($subscription->endpoint, 70) }}</span>
                            <div class="small text-body-secondary">{{ $subscription->content_encoding ?: '-' }}</div>
                        </td>
                        <td>
                            <div><span class="badge {{ $state === 'Active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $state }}</span></div>
                            <div class="mt-1">
                                @if($pushState === 'sent')
                                    <span class="badge text-bg-primary">sent</span>
                                @elseif(in_array($pushState, ['failed', 'expired'], true))
                                    <span class="badge text-bg-danger">{{ $pushState }}</span>
                                @elseif($pushState === 'pruned')
                                    <span class="badge text-bg-warning">pruned</span>
                                @else
                                    <span class="badge text-bg-light">{{ $pushState }}</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $subscription->push_attempt_count }}</td>
                        <td class="small text-body-secondary">{{ $subscription->last_push_attempt_at?->diffForHumans() ?? '-' }}</td>
                        <td class="small text-body-secondary">{{ $subscription->last_push_success_at?->diffForHumans() ?? '-' }}</td>
                        <td class="small text-body-secondary">{{ $subscription->disabled_at?->diffForHumans() ?? '-' }}</td>
                        <td class="small text-danger">{{ \Illuminate\Support\Str::limit((string) $subscription->last_push_error, 80) ?: '-' }}</td>
                        <td class="text-end">
                            @if($subscription->user)
                                <form method="POST" action="{{ route('notifications.push-report.test-user', $subscription->user) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Test User</button>
                                </form>
                            @else
                                <span class="text-body-secondary small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-body-secondary py-3">No push subscriptions found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($subscriptions->hasPages())
        <div class="card-footer">
            {{ $subscriptions->links() }}
        </div>
    @endif
</div>
@endsection
