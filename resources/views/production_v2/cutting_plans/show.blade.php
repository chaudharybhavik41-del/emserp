@extends('layouts.erp')

@section('title', 'Production V2 Cutting Plan')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">{{ $plan->plan_number }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('production.plan.update')
            @if(!in_array($plan->status, ['released', 'superseded'], true))
                <a href="{{ route('projects.production-v2.cutting-plans.edit', ['project' => $project->id, 'cuttingPlan' => $plan->id]) }}" class="btn btn-sm btn-primary">Edit</a>
            @elseif(in_array($plan->status, ['released', 'superseded'], true))
                <form method="POST" action="{{ route('projects.production-v2.cutting-plans.revise', ['project' => $project->id, 'cuttingPlan' => $plan->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">Create Revision</button>
                </form>
            @endif
        @endcan
        <a href="{{ route('projects.production-v2.cutting-plans.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">
            Back
        </a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.design_nav', ['project' => $project, 'active' => 'cutting_plans'])
    @include('production_v2.partials.mobile_list_styles')

    @if($dependencyImpact->isNotEmpty())
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Outdated Part Revisions</div>
        <div class="small">
            This cutting plan still allocates older part revisions: {{ $dependencyImpact->pluck('partDefinition.part_code')->filter()->unique()->implode(', ') }}.
            Create a new cutting-plan revision before further release/use.
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
                    <div class="display-6 mb-0">R{{ $plan->revision_no ?: 1 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-primary-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-primary mb-1">Grade / Thickness</div>
                    <div class="fw-semibold">{{ $plan->grade ?: '-' }}</div>
                    <div class="small text-body-secondary">{{ $plan->thickness_mm ?: '-' }} mm</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-success-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-success mb-1">Allocations</div>
                    <div class="display-6 mb-0">{{ number_format($plan->allocations->count()) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-dark-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Status</div>
                    <div class="fw-semibold">{{ ucfirst($plan->status) }}</div>
                    <div class="small text-body-secondary">{{ $plan->designRelease?->release_number ?: 'Not released yet' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Date</div><div>{{ $plan->plan_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Grade</div><div>{{ $plan->grade ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Thickness</div><div>{{ $plan->thickness_mm ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Source</div><div>{{ str_replace('_', ' ', $plan->source_mode) }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Status</div><div>{{ ucfirst($plan->status) }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Allocations</div><div>{{ number_format($plan->allocations->count()) }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Previous Revision</div><div>{{ $plan->previousRevision?->plan_number ? $plan->previousRevision->plan_number . ' / R' . $plan->previousRevision->revision_no : '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released In</div><div>{{ $plan->designRelease?->release_number ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Released By</div><div>{{ $plan->releasedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Superseded By</div><div>{{ $plan->supersededByRevision?->plan_number ? $plan->supersededByRevision->plan_number . ' / R' . $plan->supersededByRevision->revision_no : '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $plan->remarks ?: '-' }}</div></div>
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
                            <td class="text-end"><a href="{{ route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
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
                            <a href="{{ route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $row->id]) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Allocations</div>
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Part</th>
                            <th class="text-end">Qty</th>
                            <th>Planned Blank</th>
                            <th>Cut Size</th>
                            <th>W</th>
                            <th>L</th>
                            <th>T</th>
                            <th>Group</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($plan->allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->partDefinition?->part_code }}<div class="small text-body-secondary">{{ $allocation->partDefinition?->part_name }}</div></td>
                            <td class="text-end">{{ number_format((float) $allocation->planned_qty, 3) }}</td>
                            <td>
                                {{ $allocation->planned_blank_ref ?: ($allocation->allocation_group ?: ($allocation->motherStock?->plate_number ?: '-')) }}
                                <div class="small text-body-secondary">
                                    {{ $allocation->planned_blank_width_mm ?: '-' }} x {{ $allocation->planned_blank_length_mm ?: '-' }} mm
                                </div>
                            </td>
                            <td>{{ $allocation->cut_size_text ?: '-' }}</td>
                            <td>{{ $allocation->cut_width_mm ?: '-' }}</td>
                            <td>{{ $allocation->cut_length_mm ?: '-' }}</td>
                            <td>{{ $allocation->thickness_mm ?: '-' }}</td>
                            <td>{{ $allocation->allocation_group ?: '-' }}</td>
                            <td>{{ $allocation->remarks ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No allocations added.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pv2-mobile-list p-3">
                @forelse($plan->allocations as $allocation)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $allocation->partDefinition?->part_code ?: 'Part' }}</div>
                                <div class="small text-body-secondary">{{ $allocation->partDefinition?->part_name ?: 'No part name' }}</div>
                            </div>
                            <span class="badge text-bg-light border">{{ number_format((float) $allocation->planned_qty, 3) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Planned Blank</span>
                                <span>{{ $allocation->planned_blank_ref ?: ($allocation->allocation_group ?: ($allocation->motherStock?->plate_number ?: '-')) }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Blank Size</span>
                                <span>{{ $allocation->planned_blank_width_mm ?: '-' }} x {{ $allocation->planned_blank_length_mm ?: '-' }} mm</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Cut Size</span>
                                <span>{{ $allocation->cut_size_text ?: '-' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">W / L / T</span>
                                <span>{{ $allocation->cut_width_mm ?: '-' }} / {{ $allocation->cut_length_mm ?: '-' }} / {{ $allocation->thickness_mm ?: '-' }}</span>
                            </div>
                            <div class="pv2-mobile-card__row">
                                <span class="pv2-mobile-card__label">Group</span>
                                <span>{{ $allocation->allocation_group ?: '-' }}</span>
                            </div>
                        </div>
                        <div class="small text-body-secondary mt-3">{{ $allocation->remarks ?: 'No remarks' }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No allocations added.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
