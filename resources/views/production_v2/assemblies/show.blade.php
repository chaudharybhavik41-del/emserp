@extends('layouts.erp')

@section('title', 'Production V2 Assembly')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $assembly->assembly_code }} - {{ $assembly->assembly_name }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('production.plan.update')
            @if(!in_array($assembly->status, ['released', 'superseded'], true))
            <a href="{{ route('projects.production-v2.assemblies.edit', ['project' => $project->id, 'assembly' => $assembly->id]) }}" class="btn btn-sm btn-primary">Edit</a>
            @elseif(in_array($assembly->status, ['released', 'superseded'], true))
            <form method="POST" action="{{ route('projects.production-v2.assemblies.revise', ['project' => $project->id, 'assembly' => $assembly->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">Create Revision</button>
            </form>
            @endif
        @endcan
        <a href="{{ route('projects.production-v2.assemblies.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'assemblies'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="alert alert-info">
        Production route and process planning are managed in the production module.
        <a href="{{ route('projects.production-v2.route-planning.index', ['project' => $project->id]) }}" class="alert-link">Open Production Route Planning</a>
    </div>

    @if($dependencyImpact->isNotEmpty())
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Outdated Part Revisions</div>
        <div class="small">
            This assembly still consumes older part revisions: {{ $dependencyImpact->pluck('partDefinition.part_code')->filter()->unique()->implode(', ') }}.
            Create a new assembly revision and replace these requirements before the next release.
        </div>
        <div class="small mt-2">
            Guided correction in the revision action will prefill the draft and swap these stale part references to the latest released revision where available.
        </div>
    </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Revision</div>
                    <div class="display-6 mb-0">R{{ $assembly->revision_no ?: 1 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-success-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-success mb-1">Requirements</div>
                    <div class="display-6 mb-0">{{ number_format($assembly->requirements->count()) }}</div>
                    <div class="small text-body-secondary">Part lines in this revision</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-dark-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Status</div>
                    <div class="fw-semibold">{{ ucfirst($assembly->status) }}</div>
                    <div class="small text-body-secondary">{{ $assembly->designRelease?->release_number ?: 'Not released yet' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Type</div><div>{{ $assembly->assembly_type ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Span</div><div>{{ $assembly->span_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Segment</div><div>{{ $assembly->segment_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Girder</div><div>{{ $assembly->girder_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Status</div><div>{{ ucfirst($assembly->status) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Previous Revision</div><div>{{ $assembly->previousRevision?->assembly_code ? $assembly->previousRevision->assembly_code . ' / R' . $assembly->previousRevision->revision_no : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Sequence</div><div>{{ $assembly->sequence_no }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Planned Qty</div><div>{{ number_format((float) $assembly->planned_qty, 3) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Planned Weight</div><div>{{ $assembly->planned_weight_kg ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Drawing Ref</div><div>{{ $assembly->drawing_ref ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released In</div><div>{{ $assembly->designRelease?->release_number ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released By</div><div>{{ $assembly->releasedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Superseded By</div><div>{{ $assembly->supersededByRevision?->assembly_code ? $assembly->supersededByRevision->assembly_code . ' / R' . $assembly->supersededByRevision->revision_no : '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $assembly->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Revision History</div>
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
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
                            <td class="text-end"><a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pv2-mobile-list p-3">
                @foreach($revisionHistory as $row)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div class="pv2-mobile-card__title">R{{ $row->revision_no ?: 1 }}</div>
                            <span class="badge text-bg-light border">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Assembly Part Requirements</div>
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Part</th>
                            <th class="text-end">Qty</th>
                            <th>UOM</th>
                            <th>Mandatory</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($assembly->requirements as $requirement)
                        <tr>
                            <td>
                                {{ $requirement->partDefinition?->part_code }}
                                <div class="small text-body-secondary">{{ $requirement->partDefinition?->part_name }}</div>
                            </td>
                            <td class="text-end">{{ number_format((float) $requirement->required_qty, 3) }}</td>
                            <td>{{ $requirement->uom?->code ?: $requirement->partDefinition?->uom?->code }}</td>
                            <td>{{ $requirement->is_mandatory ? 'Yes' : 'No' }}</td>
                            <td>{{ $requirement->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No part requirements assigned.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pv2-mobile-list p-3">
                @forelse($assembly->requirements as $requirement)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $requirement->partDefinition?->part_code ?: 'Part' }}</div>
                                <div class="small text-body-secondary">{{ $requirement->partDefinition?->part_name ?: 'No name' }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ $requirement->is_mandatory ? 'Mandatory' : 'Optional' }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Qty</span>
                                <span>{{ number_format((float) $requirement->required_qty, 3) }} {{ $requirement->uom?->code ?: $requirement->partDefinition?->uom?->code }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Remarks</span>
                                <span>{{ $requirement->remarks ?: '-' }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No part requirements assigned.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
