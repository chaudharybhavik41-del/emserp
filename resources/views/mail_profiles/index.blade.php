@extends('layouts.erp')

@section('title', 'Mail Profiles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Mail Profiles</h1>

    @can('core.mail_profile.create')
        <a href="{{ route('mail-profiles.create') }}" class="btn btn-primary btn-sm">
            + Add Mail Profile
        </a>
    @endcan
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('mail-profiles.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Search</label>
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control form-control-sm"
                       placeholder="Code / Name / Email / SMTP host">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Scope</label>
                <select name="scope" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="global" {{ request('scope') === 'global' ? 'selected' : '' }}>Global</option>
                    <option value="company" {{ request('scope') === 'company' ? 'selected' : '' }}>Company</option>
                    <option value="department" {{ request('scope') === 'department' ? 'selected' : '' }}>Department</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Company</label>
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ (string) request('company_id') === (string) $company->id ? 'selected' : '' }}>
                            {{ $company->code }} — {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Default</label>
                <select name="default" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="default" {{ request('default') === 'default' ? 'selected' : '' }}>Default</option>
                    <option value="non_default" {{ request('default') === 'non_default' ? 'selected' : '' }}>Non-default</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="{{ route('mail-profiles.index') }}" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

@php
    $sort = (string) request('sort', '');
    $dir = strtolower((string) request('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

    $sortLink = function (string $col) use ($sort, $dir) {
        $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir]);
    };
@endphp

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 10%">
                    <a href="{{ $sortLink('code') }}" class="text-decoration-none text-dark">
                        Code
                        @if($sort === 'code')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th>
                    <a href="{{ $sortLink('name') }}" class="text-decoration-none text-dark">
                        Name
                        @if($sort === 'name')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 20%">
                    <a href="{{ $sortLink('from_email') }}" class="text-decoration-none text-dark">
                        From Email
                        @if($sort === 'from_email')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 15%">Scope</th>
                <th style="width: 10%">
                    <a href="{{ $sortLink('is_default') }}" class="text-decoration-none text-dark">
                        Default
                        @if($sort === 'is_default')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 10%">
                    <a href="{{ $sortLink('is_active') }}" class="text-decoration-none text-dark">
                        Status
                        @if($sort === 'is_active')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 20%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($profiles as $profile)
                <tr>
                    <td>{{ $profile->code }}</td>
                    <td>{{ $profile->name }}</td>
                    <td>{{ $profile->from_email }}</td>
                    <td>
                        @if($profile->department)
                            {{ $profile->department->code }} (Dept)
                        @elseif($profile->company)
                            {{ $profile->company->code }} (Company)
                        @else
                            <span class="text-muted">Global</span>
                        @endif
                    </td>
                    <td>
                        @if($profile->is_default)
                            <span class="badge text-bg-primary">Default</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($profile->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('core.mail_profile.update')
                            <form action="{{ route('mail-profiles.test', $profile) }}"
                                  method="POST"
                                  class="d-inline">
                                @csrf
                                <input type="email"
                                       name="test_email"
                                       class="form-control form-control-sm d-inline-block w-auto me-1"
                                       placeholder="test@example.com"
                                       required>
                                <button class="btn btn-sm btn-outline-secondary">
                                    Test
                                </button>
                            </form>
                            <a href="{{ route('mail-profiles.edit', $profile) }}"
                               class="btn btn-sm btn-outline-primary ms-1">
                                Edit
                            </a>
                        @endcan

                        @can('core.mail_profile.delete')
                            <form action="{{ route('mail-profiles.destroy', $profile) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this mail profile?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger ms-1">
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-3">
                        No mail profiles found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($profiles->hasPages())
        <div class="card-footer">
            {{ $profiles->links() }}
        </div>
    @endif
</div>
@endsection
