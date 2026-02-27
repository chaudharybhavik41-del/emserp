@extends('layouts.erp')

@section('title', 'Material Subcategories')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Material Subcategories</h1>

    @can('core.material_subcategory.create')
        <a href="{{ route('material-subcategories.create') }}" class="btn btn-primary btn-sm">
            + Add Subcategory
        </a>
    @endcan
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('material-subcategories.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label mb-1">Search</label>
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control form-control-sm"
                       placeholder="Code / Name / Category / Description">
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Category</label>
                <select name="material_category_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ (string) request('material_category_id') === (string) $cat->id ? 'selected' : '' }}>
                            {{ $cat->type?->code }} — {{ $cat->code }} ({{ $cat->name }})
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
                <a href="{{ route('material-subcategories.index') }}" class="btn btn-sm btn-outline-secondary">
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
                <th style="width: 10%">Type</th>
                <th style="width: 12%">
                    <a href="{{ $sortLink('material_category_id') }}" class="text-decoration-none text-dark">
                        Category
                        @if($sort === 'material_category_id')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
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
                <th style="width: 18%">Description</th>
                <th style="width: 15%">Expense Account</th>
                <th style="width: 6%">
                    <a href="{{ $sortLink('sort_order') }}" class="text-decoration-none text-dark">
                        Sort
                        @if($sort === 'sort_order')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 6%">
                    <a href="{{ $sortLink('is_active') }}" class="text-decoration-none text-dark">
                        Active
                        @if($sort === 'is_active')
                            <i class="bi {{ $dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down' }} small"></i>
                        @endif
                    </a>
                </th>
                <th style="width: 13%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($subcategories as $subcategory)
                <tr>
                    <td>{{ $subcategory->category?->type?->code }}</td>
                    <td>{{ $subcategory->category?->code }}</td>
                    <td>{{ $subcategory->code }}</td>
                    <td>{{ $subcategory->name }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($subcategory->description, 40) }}</td>
                    <td>{{ $subcategory->expenseAccount?->name ?? '—' }}</td>
                    <td>{{ $subcategory->sort_order }}</td>
                    <td>
                        @if($subcategory->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('core.material_subcategory.update')
                            <a href="{{ route('material-subcategories.edit', $subcategory) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan

                        @can('core.material_subcategory.delete')
                            <form action="{{ route('material-subcategories.destroy', $subcategory) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this material subcategory?');">
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
                    <td colspan="9" class="text-center text-muted py-3">
                        No material subcategories found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($subcategories->hasPages())
        <div class="card-footer">
            {{ $subcategories->links() }}
        </div>
    @endif
</div>
@endsection
