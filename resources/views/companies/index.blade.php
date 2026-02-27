@extends('layouts.erp')

@section('title', 'Companies')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Companies</h1>

    @can('core.company.update')
        <a href="{{ route('companies.create') }}" class="btn btn-primary btn-sm">
            + Add Company
        </a>
    @endcan
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('companies.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label mb-1">Search</label>
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control form-control-sm"
                       placeholder="Code / Name / GST / Email / Phone">
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

            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="{{ route('companies.index') }}" class="btn btn-sm btn-outline-secondary">
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
                    <a href="{{ $sortLink('gst_number') }}" class="text-decoration-none text-dark">
                        GSTIN
                        @if($sort === 'gst_number')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
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
                <th style="width: 15%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($companies as $company)
                <tr>
                    <td>{{ $company->code }}</td>
                    <td>{{ $company->name }}</td>
                    <td>{{ $company->gst_number ?? '-' }}</td>
                    <td>
                        @if($company->is_default)
                            <span class="badge text-bg-primary">Default</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($company->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('core.company.update')
                            <a href="{{ route('companies.edit', $company) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        No companies found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($companies->hasPages())
        <div class="card-footer">
            {{ $companies->links() }}
        </div>
    @endif
</div>
@endsection
