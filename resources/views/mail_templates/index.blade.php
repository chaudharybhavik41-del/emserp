@extends('layouts.erp')

@section('title', 'Mail Templates')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Mail Templates</h1>

    @can('core.mail_template.create')
        <a href="{{ route('mail-templates.create') }}" class="btn btn-primary btn-sm">
            + Add Mail Template
        </a>
    @endcan
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('mail-templates.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Search</label>
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control form-control-sm"
                       placeholder="Code / Name / Subject / Type">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" {{ (string) request('type') === (string) $t ? 'selected' : '' }}>
                            {{ $t }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label mb-1">Profile</label>
                <select name="mail_profile_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="0" {{ (string) request('mail_profile_id') === '0' ? 'selected' : '' }}>Default / None</option>
                    @foreach($profiles as $p)
                        <option value="{{ $p->id }}" {{ (string) request('mail_profile_id') === (string) $p->id ? 'selected' : '' }}>
                            {{ $p->code }} — {{ $p->name }}
                        </option>
                    @endforeach
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

            <div class="col-12 col-md-1 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Go
                </button>
            </div>

            <div class="col-12">
                <a href="{{ route('mail-templates.index') }}" class="btn btn-sm btn-outline-secondary">
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
                <th style="width: 15%">
                    <a href="{{ $sortLink('type') }}" class="text-decoration-none text-dark">
                        Type
                        @if($sort === 'type')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 25%">
                    <a href="{{ $sortLink('subject') }}" class="text-decoration-none text-dark">
                        Subject
                        @if($sort === 'subject')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 15%">Profile</th>
                <th style="width: 10%">
                    <a href="{{ $sortLink('is_active') }}" class="text-decoration-none text-dark">
                        Status
                        @if($sort === 'is_active')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 15%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($templates as $template)
                <tr>
                    <td>{{ $template->code }}</td>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->type ?? '-' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($template->subject, 40) }}</td>
                    <td>
                        @if($template->mailProfile)
                            {{ $template->mailProfile->code }}
                        @else
                            <span class="text-muted">Default</span>
                        @endif
                    </td>
                    <td>
                        @if($template->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('core.mail_template.update')
                            <a href="{{ route('mail-templates.edit', $template) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan

                        @can('core.mail_template.delete')
                            <form action="{{ route('mail-templates.destroy', $template) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this mail template?');">
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
                        No mail templates found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($templates->hasPages())
        <div class="card-footer">
            {{ $templates->links() }}
        </div>
    @endif
</div>
@endsection
