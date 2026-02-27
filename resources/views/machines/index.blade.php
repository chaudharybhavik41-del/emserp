@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-gear-fill"></i> Machines</h2>
        @can('machinery.machine.create')
        <a href="{{ route('machines.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Machine
        </a>
        @endcan
    </div>

    <!-- Search & Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('machines.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="Search code, name, serial..." 
                           value="{{ request('q') }}">
                </div>
                <div class="col-md-3">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assignment" class="form-select">
                        <option value="">All Assignments</option>
                        <option value="available" {{ request('assignment') == 'available' ? 'selected' : '' }}>Available</option>
                        <option value="issued" {{ request('assignment') == 'issued' ? 'selected' : '' }}>Issued</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Machines Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Make/Model</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Assignment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($machines as $machine)
                        <tr>
                            <td>
                                <a href="{{ route('machines.show', $machine) }}">
                                    <strong>{{ $machine->code }}</strong>
                                </a>
                            </td>
                            <td>{{ $machine->name }}</td>
                            <td>
                                <span class="badge bg-secondary">
                                    {{ $machine->category->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                {{ $machine->make }}
                                @if($machine->model)
                                    <br><small class="text-muted">{{ $machine->model }}</small>
                                @endif
                            </td>
                            <td><small>{{ $machine->serial_number }}</small></td>
                            <td>
                                <span class="badge bg-{{ $machine->getStatusBadgeClass() }}">
                                    {{ ucfirst(str_replace('_', ' ', $machine->status)) }}
                                </span>
                            </td>
                            <td>
                                @if($machine->is_issued)
                                    <span class="badge bg-warning">
                                        <i class="bi bi-arrow-right"></i> Issued
                                    </span>
                                @else
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Available
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('machines.show', $machine) }}" 
                                       class="btn btn-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @can('machinery.machine.update')
                                    <a href="{{ route('machines.edit', $machine) }}" 
                                       class="btn btn-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan
                                    @can('machinery.machine.delete')
                                    @if(!$machine->is_issued)
                                    <form action="{{ route('machines.destroy', $machine) }}" 
                                          method="POST" 
                                          onsubmit="return confirm('Delete this machine?');"
                                          class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted">No machines found.</p>
                                @can('machinery.machine.create')
                                <a href="{{ route('machines.create') }}" class="btn btn-primary">
                                    Add First Machine
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-3">
                {{ $machines->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
