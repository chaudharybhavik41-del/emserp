@extends('layouts.erp')

@section('title', 'Departments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Departments</h1>

    @can('core.department.create')
        <a href="{{ route('departments.create') }}" class="btn btn-primary btn-sm">
            + Add Department
        </a>
    @endcan
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 15%">Code</th>
                <th>Name</th>
                <th style="width: 15%">Status</th>
                <th style="width: 20%" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($departments as $department)
                <tr>
                    <td>{{ $department->code }}</td>
                    <td>{{ $department->name }}</td>
                    <td>
                        @if($department->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('core.department.update')
                            <a href="{{ route('departments.edit', $department) }}"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>
                        @endcan

                        @can('core.department.delete')
                            <form action="{{ route('departments.destroy', $department) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Are you sure you want to delete this department?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    Delete
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-3">
                        No departments found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($departments->hasPages())
        <div class="card-footer">
            {{ $departments->links() }}
        </div>
    @endif
</div>
@endsection
