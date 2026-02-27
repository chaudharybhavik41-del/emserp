@extends('layouts.erp')

@section('title', 'Users')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Users</h1>
        @can('core.user.create')
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add User
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control" placeholder="Search name, email, code..." 
                           value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="trashed" {{ request('status') === 'trashed' ? 'selected' : '' }}>Deleted</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ request('role') == $role->id ? 'selected' : '' }}>
                            {{ ucfirst($role->name) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Users Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px"></th>
                        <th>User</th>
                        <th>Employee Code</th>
                        <th>Roles</th>
                        <th>Departments</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr class="{{ $user->trashed() ? 'table-danger' : (!$user->is_active ? 'table-warning' : '') }}">
                        <td>
                            @if($user->profile_photo)
                                <img src="{{ Storage::url($user->profile_photo) }}" 
                                     class="rounded-circle" width="40" height="40" alt="{{ $user->name }}">
                            @else
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                     style="width: 40px; height: 40px; font-size: 14px;">
                                    {{ $user->initials }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <small class="text-muted">{{ $user->email }}</small>
                            @if($user->phone)
                            <br><small class="text-muted"><i class="bi bi-phone"></i> {{ $user->phone }}</small>
                            @endif
                        </td>
                        <td>
                            <code>{{ $user->employee_code ?? '-' }}</code>
                            @if($user->designation)
                            <br><small class="text-muted">{{ $user->designation }}</small>
                            @endif
                        </td>
                        <td>
                            @foreach($user->roles as $role)
                            <span class="badge bg-primary">{{ ucfirst($role->name) }}</span>
                            @endforeach
                        </td>
                        <td>
                            @foreach($user->departments as $dept)
                            <span class="badge {{ $dept->pivot->is_primary ? 'bg-success' : 'bg-secondary' }}">
                                {{ $dept->name }}
                            </span>
                            @endforeach
                        </td>
                        <td>
                            @if($user->trashed())
                                <span class="badge bg-danger">Deleted</span>
                            @elseif($user->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-warning text-dark">Inactive</span>
                            @endif
                        </td>
                        <td>
                            @if($user->last_login_at)
                                <small>{{ $user->last_login_at->diffForHumans() }}</small>
                                <br><small class="text-muted">{{ $user->last_login_ip }}</small>
                            @else
                                <small class="text-muted">Never</small>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                @if($user->trashed())
                                    @can('core.user.delete')
                                    <form action="{{ route('users.restore', $user->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('users.force-delete', $user->id) }}" method="POST" 
                                          class="d-inline" onsubmit="return confirm('Permanently delete this user? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Permanent Delete">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                    @endcan
                                @else
                                    @can('core.user.view')
                                    <a href="{{ route('users.show', $user) }}" class="btn btn-outline-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan
                                    @can('core.user.update')
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($user->id !== auth()->id())
                                    <form action="{{ route('users.toggle-status', $user) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-{{ $user->is_active ? 'warning' : 'success' }}" 
                                                title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="bi bi-{{ $user->is_active ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                    @can('core.user.delete')
                                    @if($user->id !== auth()->id())
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this user?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No users found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div class="card-footer">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
