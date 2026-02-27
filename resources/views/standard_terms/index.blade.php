@extends('layouts.erp')

@section('title', 'Standard Terms & Conditions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Standard Terms &amp; Conditions</h1>
        <a href="{{ route('standard-terms.create') }}" class="btn btn-sm btn-primary">
            Add Template
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text" name="module" class="form-control form-control-sm"
                   placeholder="Filter by module (e.g. purchase)"
                   value="{{ request('module') }}">
        </div>
        <div class="col-md-3">
            <input type="text" name="sub_module" class="form-control form-control-sm"
                   placeholder="Filter by sub-module (e.g. po, rfq)"
                   value="{{ request('sub_module') }}">
        </div>
        <div class="col-md-3">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Module</th>
                    <th>Sub Module</th>
                    <th>Version</th>
                    <th>Default</th>
                    <th>Active</th>
                    <th>Sort</th>
                    <th style="width: 120px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($terms as $term)
                    <tr>
                        <td>{{ $term->code }}</td>
                        <td>{{ $term->name }}</td>
                        <td>{{ $term->module }}</td>
                        <td>{{ $term->sub_module ?? '-' }}</td>
                        <td>{{ $term->version }}</td>
                        <td>
                            @if($term->is_default)
                                <span class="badge bg-success">Default</span>
                            @endif
                        </td>
                        <td>
                            @if($term->is_active)
                                <span class="badge bg-primary">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $term->sort_order }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('standard-terms.edit', $term) }}"
                                   class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('standard-terms.destroy', $term) }}" method="POST"
                                      onsubmit="return confirm('Deactivate this template?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-3">No templates found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($terms->hasPages())
            <div class="card-footer py-2">
                {{ $terms->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endsection
