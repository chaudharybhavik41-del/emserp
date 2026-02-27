@extends('layouts.erp')

@section('title', 'Storage Access')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Storage Access</h1>
        <div class="text-muted small">
            Grant/revoke <code>{{ $storagePermission }}</code> at user level.
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @if(\Illuminate\Support\Facades\Route::has('access.roles.index'))
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('access.roles.index') }}">
                <i class="bi bi-arrow-left me-1"></i> Back to Access Control
            </a>
        @endif
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Search name/email…" />
            <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
            @if($q)
                <a class="btn btn-sm btn-outline-light border" href="{{ route('access.storage-access.index') }}">Clear</a>
            @endif
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="fw-semibold">Users</div>
        <div class="text-muted small">Total: {{ $users->total() }}</div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">User</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th class="text-center">Has Storage Access</th>
                        <th class="text-end" style="min-width: 160px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    @php
                        $hasAccess = $u->can($storagePermission); // effective permission (role OR direct)
                        $direct = method_exists($u, 'getDirectPermissions')
                            ? $u->getDirectPermissions()->pluck('name')->contains($storagePermission)
                            : false;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $u->name ?? '—' }}</td>
                        <td>{{ $u->email ?? '—' }}</td>
                        <td>
                            @forelse($u->roles as $r)
                                <span class="badge bg-light text-dark border">{{ $r->name }}</span>
                            @empty
                                <span class="text-muted small">No role</span>
                            @endforelse
                        </td>
                        <td class="text-center">
                            @if($hasAccess)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif

                            @if($direct)
                                <span class="badge bg-info text-dark">Direct</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('access.storage-access.update', $u) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="grant" value="{{ $hasAccess ? 0 : 1 }}">
                                <button type="submit"
                                        class="btn btn-sm {{ $hasAccess ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    {{ $hasAccess ? 'Revoke' : 'Grant' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $users->links() }}
</div>
@endsection
