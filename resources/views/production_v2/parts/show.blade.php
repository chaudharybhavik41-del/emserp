@extends('layouts.erp')

@section('title', 'Production V2 Part')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $part->part_code }} - {{ $part->part_name }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('production.plan.update')
            @if(!in_array($part->status, ['released', 'superseded'], true))
            <a href="{{ route('projects.production-v2.parts.edit', ['project' => $project->id, 'part' => $part->id]) }}" class="btn btn-sm btn-primary">Edit</a>
            @elseif(in_array($part->status, ['released', 'superseded'], true))
            <form method="POST" action="{{ route('projects.production-v2.parts.revise', ['project' => $project->id, 'part' => $part->id]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">Create Revision</button>
            </form>
            @endif
        @endcan
        <a href="{{ route('projects.production-v2.parts.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'parts'])
    @include('production_v2.partials.mobile_list_styles')

    <div class="alert alert-info">
        Production route and process planning are managed in the production module.
        <a href="{{ route('projects.production-v2.route-planning.index', ['project' => $project->id]) }}" class="alert-link">Open Production Route Planning</a>
    </div>

    @if($usageImpact['impacted_assemblies']->isNotEmpty() || $usageImpact['impacted_cutting_plans']->isNotEmpty() || $usageImpact['impacted_material_requirements']->isNotEmpty())
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Revision Impact</div>
        <div class="small">
            This revision is no longer the active released version. Downstream design records are still linked to it and should be revised.
        </div>
        <div class="small mt-2">
            Assemblies: {{ $usageImpact['impacted_assemblies']->pluck('assembly_code')->implode(', ') ?: '-' }} |
            Cutting Plans: {{ $usageImpact['impacted_cutting_plans']->pluck('plan_number')->implode(', ') ?: '-' }} |
            Material Requirements: {{ $usageImpact['impacted_material_requirements']->pluck('requirement_number')->implode(', ') ?: '-' }}
        </div>
        @can('production.plan.update')
        <div class="d-flex gap-2 flex-wrap mt-3">
            @foreach($usageImpact['impacted_assemblies'] as $impactedAssembly)
                @if(in_array($impactedAssembly->status, ['released', 'superseded'], true))
                <form method="POST" action="{{ route('projects.production-v2.assemblies.revise', ['project' => $project->id, 'assembly' => $impactedAssembly->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-dark">Revise {{ $impactedAssembly->assembly_code }}</button>
                </form>
                @endif
            @endforeach
            @foreach($usageImpact['impacted_cutting_plans'] as $impactedPlan)
                @if(in_array($impactedPlan->status, ['released', 'superseded'], true))
                <form method="POST" action="{{ route('projects.production-v2.cutting-plans.revise', ['project' => $project->id, 'cuttingPlan' => $impactedPlan->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-dark">Revise {{ $impactedPlan->plan_number }}</button>
                </form>
                @endif
            @endforeach
        </div>
        <div class="small mt-2">
            Guided correction will prefill the new draft and automatically replace stale part references with the latest released revision where available.
        </div>
        @endcan
    </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Revision</div>
                    <div class="display-6 mb-0">R{{ $part->revision_no ?: 1 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-success-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-success mb-1">Qty</div>
                    <div class="display-6 mb-0">{{ number_format((float) $part->required_qty, 3) }}</div>
                    <div class="small text-body-secondary">{{ $part->uom?->code ?: 'No UOM' }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-dark-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Status</div>
                    <div class="fw-semibold">{{ ucfirst($part->status) }}</div>
                    <div class="small text-body-secondary">{{ $part->designRelease?->release_number ?: 'Not released yet' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Part Type</div><div>{{ $part->part_type }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Required Qty</div><div>{{ number_format((float) $part->required_qty, 3) }} {{ $part->uom?->code }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Material Grade</div><div>{{ $part->material_grade ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Status</div><div>{{ ucfirst($part->status) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Previous Revision</div><div>{{ $part->previousRevision?->part_code ? $part->previousRevision->part_code . ' / R' . $part->previousRevision->revision_no : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Thickness</div><div>{{ $part->thickness_mm ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Width</div><div>{{ $part->width_mm ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Length</div><div>{{ $part->length_mm ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Unit Weight</div><div>{{ $part->unit_weight_kg !== null ? number_format((float) $part->unit_weight_kg, 3) . ' kg' : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Material Item</div><div>{{ $part->materialItem?->code ? $part->materialItem->code . ' - ' : '' }}{{ $part->materialItem?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Item Density</div><div>{{ $part->materialItem?->density !== null ? number_format((float) $part->materialItem->density, 3) . ' kg/m3' : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Item Wt/M</div><div>{{ $part->materialItem?->weight_per_meter !== null ? number_format((float) $part->materialItem->weight_per_meter, 3) : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released In</div><div>{{ $part->designRelease?->release_number ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released By</div><div>{{ $part->releasedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Superseded By</div><div>{{ $part->supersededByRevision?->part_code ? $part->supersededByRevision->part_code . ' / R' . $part->supersededByRevision->revision_no : '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Description</div><div>{{ $part->description ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $part->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Revision History</div>
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Revision</th>
                            <th>Status</th>
                            <th>Release</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($revisionHistory as $row)
                        <tr>
                            <td>R{{ $row->revision_no ?: 1 }}</td>
                            <td>{{ $row->status }}</td>
                            <td>{{ $row->design_release_id ? ('Release #' . $row->design_release_id) : '-' }}</td>
                            <td class="text-end"><a href="{{ route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
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
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Release</span>
                                <span>{{ $row->design_release_id ? ('Release #' . $row->design_release_id) : '-' }}</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
