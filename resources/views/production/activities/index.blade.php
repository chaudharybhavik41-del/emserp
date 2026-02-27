@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="bi bi-diagram-3"></i> Production Activities</h2>
        @can('production.activity.create')
            <a href="{{ route('production.activities.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Activity
            </a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('production.activities.index') }}" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="q" class="form-control" placeholder="Search code or name" value="{{ $q }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-secondary w-100"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Seq</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Applies To</th>
                            <th>Billing</th>
                            <th>Flags</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $a)
                            <tr>
                                <td>{{ $a->default_sequence }}</td>
                                <td><span class="fw-semibold">{{ $a->code }}</span></td>
                                <td>{{ $a->name }}</td>
                                <td class="text-capitalize">{{ $a->applies_to }}</td>
                                <td>
                                    <div class="small text-muted">{{ $a->calculation_method }}</div>
                                    <div>{{ $a->billingUom?->code ?? '—' }}</div>
                                </td>
                                <td>
                                    @if($a->is_fitupp)
                                        <span class="badge text-bg-info">Fitup</span>
                                    @endif
                                    @if($a->requires_machine)
                                        <span class="badge text-bg-secondary">Machine</span>
                                    @endif
                                    @if($a->requires_qc)
                                        <span class="badge text-bg-warning">QC</span>
                                    @endif
                                </td>
                                <td>
                                    @if($a->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-dark">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('production.activity.update')
                                        <a href="{{ route('production.activities.edit', $a) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('production.activity.delete')
                                        <form action="{{ route('production.activities.destroy', $a) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Disable this activity?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" {{ $a->is_active ? '' : 'disabled' }}>
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No activities found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $activities->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
