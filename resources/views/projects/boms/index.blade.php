@extends('layouts.erp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">BOMs for Project: {{ $project->code }} - {{ $project->name }}</h4>
        <small class="text-muted">Client: {{ optional($project->client)->name }}</small>
    </div>
    <div>
        @can('project.bom.create')
            <a href="{{ route('projects.boms.create', $project) }}" class="btn btn-primary">
                Create BOM
            </a>
        @endcan
        <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary">
            Back to Project
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        Filters
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('projects.boms.index', $project) }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}"
                            @selected($statusFilter === $status->value)>
                            {{ ucfirst($status->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary" type="submit">
                    Apply
                </button>
                <a href="{{ route('projects.boms.index', $project) }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        BOM List
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>BOM No</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Total Weight</th>
                    <th>Finalized Date</th>
                    <th>Finalized By</th>
                    <th>Remarks</th>
                    <th style="width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($boms as $bom)
                    <tr>
                        <td>{{ $bom->bom_number }}</td>
                        <td>{{ $bom->version }}</td>
                        <td>{{ ucfirst($bom->status->value) }}</td>
                        <td>{{ $bom->total_weight }}</td>
                        <td>{{ $bom->finalized_date?->format('d-m-Y') }}</td>
                        <td>{{ $bom->finalizedBy?->name }}</td>
                        <td>{{ $bom->metadata['remarks'] ?? '' }}</td>
                        <td>
                            <a href="{{ route('projects.boms.show', [$project, $bom]) }}"
                               class="btn btn-sm btn-outline-primary mb-1">
                                View
                            </a>
                            @can('project.bom.update')
                                @if($bom->isDraft())
                                    <a href="{{ route('projects.boms.edit', [$project, $bom]) }}"
                                       class="btn btn-sm btn-outline-secondary mb-1">
                                        Edit
                                    </a>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-3">
                            No BOMs yet for this project.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($boms->hasPages())
        <div class="card-footer">
            {{ $boms->links() }}
        </div>
    @endif
</div>
@endsection
