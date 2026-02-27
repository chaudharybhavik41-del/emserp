@extends('layouts.erp')

@section('title', 'Gate Passes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Gate Passes</h1>
        @can('store.gatepass.create')
            <a href="{{ route('gate-passes.create') }}" class="btn btn-sm btn-primary">
                New Gate Pass
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('gate-passes.index') }}" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="project_material" @if(request('type') === 'project_material') selected @endif>
                            Project Material
                        </option>
                        <option value="machinery_maintenance" @if(request('type') === 'machinery_maintenance') selected @endif>
                            Machinery Maintenance
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="out" @if(request('status') === 'out') selected @endif>Out</option>
                        <option value="partially_returned" @if(request('status') === 'partially_returned') selected @endif>Partially Returned</option>
                        <option value="closed" @if(request('status') === 'closed') selected @endif>Closed</option>
                        <option value="cancelled" @if(request('status') === 'cancelled') selected @endif>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects / General</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @if((string)request('project_id') === (string)$project->id) selected @endif>
                                {{ $project->code }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        Filter
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 14%">Gate Pass No</th>
                        <th style="width: 10%">Date</th>
                        <th style="width: 18%">Type</th>
                        <th style="width: 24%">Project</th>
                        <th style="width: 18%">Party</th>
                        <th style="width: 8%">Status</th>
                        <th style="width: 8%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($gatePasses as $gp)
                        <tr>
                            <td>{{ $gp->gatepass_number }}</td>
                            <td>{{ optional($gp->gatepass_date)->format('d-m-Y') }}</td>
                            <td>{{ $gp->type_label }}</td>
                            <td>
                                @if($gp->project)
                                    {{ $gp->project->code }} - {{ $gp->project->name }}
                                @else
                                    <span class="text-muted">General / Store / Outside</span>
                                @endif
                            </td>
                            <td>
                                @if($gp->type === 'project_material')
                                    @if($gp->contractor)
                                        {{ $gp->contractor->name }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                @elseif($gp->type === 'machinery_maintenance')
                                    @if($gp->toParty)
                                        {{ $gp->toParty->name }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $status = $gp->status;
                                    $label = $gp->status_label;
                                    $badgeClass = 'bg-secondary';
                                    if ($status === 'out') {
                                        $badgeClass = 'bg-warning text-dark';
                                    } elseif ($status === 'partially_returned') {
                                        $badgeClass = 'bg-info text-dark';
                                    } elseif ($status === 'closed') {
                                        $badgeClass = 'bg-success';
                                    } elseif ($status === 'cancelled') {
                                        $badgeClass = 'bg-danger';
                                    }
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('gate-passes.show', $gp) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No gate passes found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($gatePasses->hasPages())
            <div class="card-footer pb-0">
                {{ $gatePasses->links() }}
            </div>
        @endif
    </div>
@endsection
