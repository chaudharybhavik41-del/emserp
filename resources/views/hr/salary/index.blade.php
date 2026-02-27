@extends('layouts.erp')

@section('title', 'Salary Structures')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><h4 class="mb-0">Salary Structures</h4><a href="{{ route('hr.salary.create') }}" class="btn btn-primary btn-sm">Create Structure</a></div>
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Employees</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($structures as $structure)
                    <tr>
                        <td><code>{{ $structure->code }}</code></td>
                        <td>{{ $structure->name }}</td>
                        <td>{{ $structure->employees_count }}</td>
                        <td>{!! $structure->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                        <td class="text-end"><a href="{{ route('hr.salary.show', $structure) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No structures.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
