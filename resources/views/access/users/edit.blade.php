@extends('layouts.erp')

@section('title', 'Edit User Permissions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                Edit User Permissions – {{ $user->name }}
            </h1>
            <small class="text-muted">{{ $user->email }}</small>
            <div class="mt-1">
                @foreach($user->roles as $role)
                    <span class="badge bg-secondary me-1">{{ $role->name }}</span>
                @endforeach
            </div>
        </div>
        <div>
            <a href="{{ route('access.users.index') }}" class="btn btn-sm btn-outline-secondary">
                Back to Users
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('access.users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header">
                Direct Permissions
                <small class="text-muted d-block">
                    Role-based permissions are shown with a green badge and cannot be removed here.
                    Checkboxes control only direct permissions.
                </small>
            </div>
            <div class="card-body">
                @foreach($groupedPermissions as $group => $permissions)
                    <div class="mb-3 border rounded p-2">
                        <strong>{{ $group }}</strong>
                        <div class="row mt-2">
                            @foreach($permissions as $permission)
                                @php
                                    $isDirect = in_array($permission->name, $directPermissionNames);
                                    $viaRole  = in_array($permission->name, $viaRolesPermissionNames);
                                @endphp
                                <div class="col-md-3 col-6 mb-1">
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="permissions[]"
                                               id="perm_{{ Str::slug($permission->name, '_') }}"
                                               value="{{ $permission->name }}"
                                               {{ $isDirect ? 'checked' : '' }}>
                                        <label class="form-check-label"
                                               for="perm_{{ Str::slug($permission->name, '_') }}">
                                            {{ $permission->name }}
                                        </label>
                                        @if($viaRole)
                                            <span class="badge bg-success ms-1">via role</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        Save User Permissions
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection
