@extends('layouts.erp')

@section('title', 'Activity Logs')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Activity Logs</h1>
        <div>
            <a href="{{ route('activity-logs.export', request()->query()) }}" class="btn btn-outline-success">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('activity-logs.index') }}" class="row g-3">
                <div class="col-md-2">
                    <input type="text" name="q" class="form-control" placeholder="Search..." 
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
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        @foreach($actions as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $action)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" placeholder="From" 
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" placeholder="To" 
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Logs Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 160px">Date/Time</th>
                        <th style="width: 140px">User</th>
                        <th style="width: 100px">Action</th>
                        <th>Description</th>
                        <th style="width: 120px">IP Address</th>
                        <th style="width: 60px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            <small>{{ $log->created_at->format('d M Y') }}</small>
                            <br>
                            <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                        </td>
                        <td>
                            @if($log->user)
                                <a href="{{ route('users.show', $log->user) }}">{{ $log->user->name }}</a>
                            @else
                                <span class="text-muted">{{ $log->user_name ?? 'System' }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $log->action_color }}">{{ $log->action_label }}</span>
                        </td>
                        <td>
                            {{ Str::limit($log->description, 80) }}
                            @if($log->subject_type)
                                <br><small class="text-muted">{{ class_basename($log->subject_type) }}: {{ $log->subject_name }}</small>
                            @endif
                        </td>
                        <td>
                            <code class="small">{{ $log->ip_address }}</code>
                        </td>
                        <td>
                            <a href="{{ route('activity-logs.show', $log) }}" class="btn btn-sm btn-outline-info" title="Details">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            No activity logs found.
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
