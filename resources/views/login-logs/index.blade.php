@extends('layouts.erp')

@section('title', 'Login Logs')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Login Logs</h1>
        <div>
            <a href="{{ route('login-logs.export', request()->query()) }}" class="btn btn-outline-success">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- Statistics --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['total_logins_today'] }}</h3>
                    <small>Logins Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['failed_attempts_today'] }}</h3>
                    <small>Failed Attempts Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['unique_users_today'] }}</h3>
                    <small>Unique Users Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h3 class="mb-0">{{ $stats['locked_accounts'] }}</h3>
                    <small>Currently Locked</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('login-logs.index') }}" class="row g-3">
                <div class="col-md-2">
                    <input type="text" name="q" class="form-control" placeholder="Email or IP..." 
                           value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="event_type" class="form-select">
                        <option value="">All Events</option>
                        @foreach($eventTypes as $value => $label)
                        <option value="{{ $value }}" {{ request('event_type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="{{ route('login-logs.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Unlock Account Form --}}
    @if($stats['locked_accounts'] > 0)
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">Unlock Account</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('login-logs.unlock') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-auto">
                    <input type="email" name="email" class="form-control" placeholder="Enter email to unlock" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-unlock me-1"></i> Unlock
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Logs Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 160px">Date/Time</th>
                        <th>Email</th>
                        <th>User</th>
                        <th style="width: 140px">Event</th>
                        <th>IP Address</th>
                        <th>Browser / Platform</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="{{ $log->event_type === 'login_failed' ? 'table-danger' : ($log->event_type === 'account_locked' ? 'table-warning' : '') }}">
                        <td>
                            <small>{{ $log->created_at->format('d M Y') }}</small>
                            <br>
                            <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                        </td>
                        <td>{{ $log->email }}</td>
                        <td>
                            @if($log->user)
                                <a href="{{ route('users.show', $log->user) }}">{{ $log->user->name }}</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @switch($log->event_type)
                                @case('login_success')
                                    <span class="badge bg-success">Login Success</span>
                                    @break
                                @case('login_failed')
                                    <span class="badge bg-danger">Login Failed</span>
                                    @if($log->failure_reason)
                                        <br><small class="text-muted">{{ str_replace('_', ' ', $log->failure_reason) }}</small>
                                    @endif
                                    @break
                                @case('logout')
                                    <span class="badge bg-secondary">Logout</span>
                                    @break
                                @case('account_locked')
                                    <span class="badge bg-warning text-dark">Locked</span>
                                    @break
                                @case('account_unlocked')
                                    <span class="badge bg-info">Unlocked</span>
                                    @break
                                @default
                                    <span class="badge bg-info">{{ $log->event_type }}</span>
                            @endswitch
                        </td>
                        <td><code class="small">{{ $log->ip_address }}</code></td>
                        <td>
                            <small>{{ $log->browser }}</small>
                            <br>
                            <small class="text-muted">{{ $log->platform }}</small>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            No login logs found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
