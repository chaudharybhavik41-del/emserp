@extends('layouts.erp')

@section('title', 'User Details')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">User Details</h1>
        <div>
            @can('core.user.update')
            <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            @endcan
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        {{-- User Profile Card --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    @if($user->profile_photo)
                        <img src="{{ Storage::url($user->profile_photo) }}" 
                             class="rounded-circle mb-3" width="120" height="120" alt="{{ $user->name }}">
                    @else
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 120px; height: 120px; font-size: 48px;">
                            {{ $user->initials }}
                        </div>
                    @endif
                    
                    <h4 class="mb-1">{{ $user->name }}</h4>
                    @if($user->designation)
                    <p class="text-muted mb-2">{{ $user->designation }}</p>
                    @endif
                    
                    @if($user->is_active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-warning text-dark">Inactive</span>
                    @endif
                </div>
                <ul class="list-group list-group-flush">
                    @if($user->employee_code)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Employee Code</span>
                        <code>{{ $user->employee_code }}</code>
                    </li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Email</span>
                        <span>{{ $user->email }}</span>
                    </li>
                    @if($user->phone)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Phone</span>
                        <span>{{ $user->phone }}</span>
                    </li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Created</span>
                        <span>{{ $user->created_at->format('d M Y') }}</span>
                    </li>
                    @if($user->last_login_at)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Last Login</span>
                        <span title="{{ $user->last_login_at->format('d M Y H:i') }}">
                            {{ $user->last_login_at->diffForHumans() }}
                        </span>
                    </li>
                    @endif
                </ul>
            </div>

            {{-- Roles --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Roles</h5>
                </div>
                <div class="card-body">
                    @forelse($user->roles as $role)
                        <span class="badge bg-primary me-1 mb-1">{{ ucfirst($role->name) }}</span>
                    @empty
                        <span class="text-muted">No roles assigned</span>
                    @endforelse
                </div>
            </div>

            {{-- Departments --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Departments</h5>
                </div>
                <div class="card-body">
                    @forelse($user->departments as $dept)
                        <span class="badge {{ $dept->pivot->is_primary ? 'bg-success' : 'bg-secondary' }} me-1 mb-1">
                            {{ $dept->name }}
                            @if($dept->pivot->is_primary)
                                <i class="bi bi-star-fill ms-1" title="Primary"></i>
                            @endif
                        </span>
                    @empty
                        <span class="text-muted">No departments assigned</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Activity & Logs --}}
        <div class="col-lg-8">
            {{-- Recent Login Activity --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Login Activity</h5>
                    <a href="{{ route('login-logs.user-history', $user) }}" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Time</th>
                                <th>Event</th>
                                <th>IP Address</th>
                                <th>Browser</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($user->loginLogs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->event_type === 'login_success')
                                        <span class="badge bg-success">Login</span>
                                    @elseif($log->event_type === 'login_failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @elseif($log->event_type === 'logout')
                                        <span class="badge bg-secondary">Logout</span>
                                    @else
                                        <span class="badge bg-info">{{ $log->event_type }}</span>
                                    @endif
                                </td>
                                <td><code>{{ $log->ip_address }}</code></td>
                                <td>{{ $log->browser }} / {{ $log->platform }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No login activity</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Role History --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Role Change History</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>Role</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roleHistory as $history)
                            <tr>
                                <td>{{ $history->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($history->action === 'assigned')
                                        <span class="badge bg-success">Assigned</span>
                                    @else
                                        <span class="badge bg-danger">Removed</span>
                                    @endif
                                </td>
                                <td>{{ ucfirst($history->role->name) }}</td>
                                <td>{{ $history->performer?->name ?? 'System' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No role changes recorded</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                    <a href="{{ route('activity-logs.index', ['user_id' => $user->id]) }}" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activityLogs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge bg-{{ $log->action_color }}">{{ $log->action_label }}</span>
                                </td>
                                <td>{{ Str::limit($log->description, 60) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No activity recorded</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
