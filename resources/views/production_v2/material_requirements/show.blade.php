@extends('layouts.erp')

@section('title', 'Production V2 Material Requirement')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $materialRequirement->requirement_number }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('production.plan.update')
            @if(!in_array($materialRequirement->status, ['released', 'superseded'], true))
                <a href="{{ route('projects.production-v2.material-requirements.edit', ['project' => $project->id, 'materialRequirement' => $materialRequirement->id]) }}" class="btn btn-sm btn-primary">Edit</a>
            @elseif(in_array($materialRequirement->status, ['released', 'superseded'], true))
                <form method="POST" action="{{ route('projects.production-v2.material-requirements.revise', ['project' => $project->id, 'materialRequirement' => $materialRequirement->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">Create Revision</button>
                </form>
            @endif
        @endcan
        @if($materialRequirement->status === 'approved' && auth()->user()?->can('production.plan.create'))
            <form method="POST" action="{{ route('projects.production-v2.material-requirements.release', ['project' => $project->id, 'materialRequirement' => $materialRequirement->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">Release</button>
            </form>
        @endif
        <a href="{{ route('projects.production-v2.material-requirements.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    @if($dependencyImpact->isNotEmpty())
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Released-Design Snapshot Is Stale</div>
        <div class="small">
            Newer part revisions were released after this material requirement snapshot: {{ $dependencyImpact->pluck('part_code')->filter()->unique()->implode(', ') }}.
            Create a revision if procurement/planning must align to the latest design release.
        </div>
        <div class="small mt-2">
            Guided correction in the revision action will refresh this draft from the latest released design snapshot.
        </div>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Date</div><div>{{ $materialRequirement->requirement_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Basis</div><div>{{ $materialRequirement->basis }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Status</div><div>{{ $materialRequirement->status }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Revision</div><div>R{{ $materialRequirement->revision_no ?: 1 }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Previous Revision</div><div>{{ $materialRequirement->previousRevision?->requirement_number ? $materialRequirement->previousRevision->requirement_number . ' / R' . $materialRequirement->previousRevision->revision_no : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Release</div><div>{{ $materialRequirement->designRelease?->release_number ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Superseded By</div><div>{{ $materialRequirement->supersededByRevision?->requirement_number ? $materialRequirement->supersededByRevision->requirement_number . ' / R' . $materialRequirement->supersededByRevision->revision_no : '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $materialRequirement->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Revision History</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Revision</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($revisionHistory as $row)
                        <tr>
                            <td>R{{ $row->revision_no ?: 1 }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-end"><a href="{{ route('projects.production-v2.material-requirements.show', ['project' => $project->id, 'materialRequirement' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Requirement Items</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Material</th>
                            <th>Category</th>
                            <th>Grade</th>
                            <th>Profile</th>
                            <th class="text-end">Required Qty</th>
                            <th class="text-end">Required Weight</th>
                            <th class="text-end">Planned Cut</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($materialRequirement->items as $row)
                        <tr>
                            <td>{{ $row->materialItem?->code ? $row->materialItem->code . ' - ' . $row->materialItem->name : ($row->materialItem?->name ?: '-') }}</td>
                            <td>{{ $row->material_category ?: '-' }}</td>
                            <td>{{ $row->material_grade ?: '-' }}</td>
                            <td>{{ $row->profile_text ?: '-' }}</td>
                            <td class="text-end">{{ number_format((float) $row->required_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) ($row->required_weight_kg ?? 0), 3) }}</td>
                            <td class="text-end">{{ number_format((float) ($row->planned_cut_qty_snapshot ?? 0), 3) }}</td>
                            <td>{{ $row->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No requirement items found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
