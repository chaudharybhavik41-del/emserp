@extends('layouts.erp')

@section('title', 'Production V2 Design Workbench')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Design Workbench</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(auth()->user()?->can('production.dpr.view') || auth()->user()?->can('production.dpr.create') || auth()->user()?->can('production.qc.perform'))
        <a href="{{ route('production-v2.project', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-hammer me-1"></i>Open Production Module
        </a>
        @endif
        <a href="{{ route('projects.production-v2.import-bom.form', ['project' => $project->id]) }}" class="btn btn-sm btn-dark">
            <i class="bi bi-diagram-2 me-1"></i>Import From BOM
        </a>
        <a href="{{ route('projects.production-v2.parts.index', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-box-seam me-1"></i>Part List
        </a>
        <a href="{{ route('projects.production-v2.assemblies.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-diagram-3 me-1"></i>Assembly BOM
        </a>
        <a href="{{ route('projects.production-v2.cutting-plans.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-scissors me-1"></i>Cutting Plans / Nesting
        </a>
        <a href="{{ route('projects.production-v2.demand.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-bar-chart-line me-1"></i>Material Planning
        </a>
        <a href="{{ route('projects.production-v2.material-requirements.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-card-checklist me-1"></i>Material Requirements
        </a>
        <a href="{{ route('projects.production-v2.design-releases.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-box-arrow-up-right me-1"></i>Design Releases
        </a>
    </div>
@endsection

@section('content')
    @php $mode = $project->production_mode ?? 'legacy_only'; @endphp

    <div class="alert {{ $mode === 'v2_enabled' ? 'alert-success' : ($mode === 'legacy_to_v2_transition' ? 'alert-warning' : 'alert-secondary') }} d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <div class="fw-semibold">Design-Owned Release Layer</div>
            <div class="small">
                Design controls part list, assembly consumption map, cutting plan / nesting, and material planning. Production should execute only against released design data.
            </div>
        </div>
        @can('production.plan.update')
            <form method="POST" action="{{ route('production-v2.project.mode', ['project' => $project->id]) }}" class="d-flex gap-2">
                @csrf
                <select name="production_mode" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="legacy_only" @selected($mode === 'legacy_only')>Legacy Only</option>
                    <option value="v2_enabled" @selected($mode === 'v2_enabled')>V2 Enabled</option>
                    <option value="legacy_to_v2_transition" @selected($mode === 'legacy_to_v2_transition')>Legacy to V2 Transition</option>
                </select>
                <button type="submit" class="btn btn-sm btn-dark">Update Mode</button>
            </form>
        @endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Part Definitions</div>
                    <div class="display-6 mb-0">{{ number_format($summary['part_definitions']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Assemblies</div>
                    <div class="display-6 mb-0">{{ number_format($summary['assemblies']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Assembly Requirements</div>
                    <div class="display-6 mb-0">{{ number_format($summary['requirements']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Cutting Plans</div>
                    <div class="display-6 mb-0">{{ number_format($summary['cutting_plans']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Revision Impact Watchlist</span>
                    @can('production.plan.update')
                    <button type="submit" form="batch-correct-form" class="btn btn-sm btn-dark">Create Correction Drafts</button>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="{{ route('production-v2.project.design.batch-correct', ['project' => $project->id]) }}" id="batch-correct-form">
                        @csrf
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%">Fix</th>
                                    <th>Type</th>
                                    <th>Document</th>
                                    <th>Status</th>
                                    <th>Outdated Reference</th>
                                    <th class="text-end">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $impactCount = $revisionImpact['assemblies']->count() + $revisionImpact['cutting_plans']->count() + $revisionImpact['material_requirements']->count();
                            @endphp
                            @forelse($revisionImpact['assemblies'] as $row)
                                <tr>
                                    <td>
                                        @if(in_array($row['assembly']->status, ['released', 'superseded'], true))
                                            <input class="form-check-input" type="checkbox" name="assembly_ids[]" value="{{ $row['assembly']->id }}">
                                        @else
                                            <span class="text-muted small">Edit</span>
                                        @endif
                                    </td>
                                    <td>Assembly BOM</td>
                                    <td>
                                        <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $row['assembly']->id]) }}">
                                            {{ $row['assembly']->assembly_code }}
                                        </a>
                                    </td>
                                    <td>{{ $row['assembly']->status }}</td>
                                    <td>{{ $row['stale_requirements']->pluck('partDefinition.part_code')->filter()->unique()->implode(', ') }}</td>
                                    <td class="text-end text-danger">{{ number_format($row['stale_requirements']->count()) }}</td>
                                </tr>
                            @empty
                                @if($impactCount === 0)
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No released-design revision impacts detected.</td>
                                    </tr>
                                @endif
                            @endforelse
                            @foreach($revisionImpact['cutting_plans'] as $row)
                                <tr>
                                    <td>
                                        @if(in_array($row['cutting_plan']->status, ['released', 'superseded'], true))
                                            <input class="form-check-input" type="checkbox" name="cutting_plan_ids[]" value="{{ $row['cutting_plan']->id }}">
                                        @else
                                            <span class="text-muted small">Edit</span>
                                        @endif
                                    </td>
                                    <td>Cutting Plan</td>
                                    <td>
                                        <a href="{{ route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $row['cutting_plan']->id]) }}">
                                            {{ $row['cutting_plan']->plan_number }}
                                        </a>
                                    </td>
                                    <td>{{ $row['cutting_plan']->status }}</td>
                                    <td>{{ $row['stale_allocations']->pluck('partDefinition.part_code')->filter()->unique()->implode(', ') }}</td>
                                    <td class="text-end text-danger">{{ number_format($row['stale_allocations']->count()) }}</td>
                                </tr>
                            @endforeach
                            @foreach($revisionImpact['material_requirements'] as $row)
                                <tr>
                                    <td>
                                        @if(in_array($row['material_requirement']->status, ['released', 'superseded'], true))
                                            <input class="form-check-input" type="checkbox" name="material_requirement_ids[]" value="{{ $row['material_requirement']->id }}">
                                        @else
                                            <span class="text-muted small">Edit</span>
                                        @endif
                                    </td>
                                    <td>Material Requirement</td>
                                    <td>
                                        <a href="{{ route('projects.production-v2.material-requirements.show', ['project' => $project->id, 'materialRequirement' => $row['material_requirement']->id]) }}">
                                            {{ $row['material_requirement']->requirement_number }}
                                        </a>
                                    </td>
                                    <td>{{ $row['material_requirement']->status }}</td>
                                    <td>{{ $row['stale_roots']->pluck('part_code')->filter()->unique()->implode(', ') }}</td>
                                    <td class="text-end text-danger">{{ number_format($row['stale_roots']->count()) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Material Planning Gaps</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th class="text-end">Required</th>
                                    <th class="text-end">Planned Cut</th>
                                    <th class="text-end">Gap</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($planningGapRows as $row)
                                <tr>
                                    <td>
                                        <div>{{ $row->part_code }}</div>
                                        <div class="small text-body-secondary">{{ $row->part_name }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $row->required_qty, 3) }} {{ $row->uom_code }}</td>
                                    <td class="text-end">{{ number_format((float) $row->planned_cut_qty, 3) }}</td>
                                    <td class="text-end text-danger">{{ number_format((float) $row->planning_gap_qty, 3) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No current gap between part demand and released cutting plans.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Latest Cutting Plans</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plan</th>
                                    <th>Grade / Thickness</th>
                                    <th class="text-end">Rows</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($latestCuttingPlans as $plan)
                                <tr>
                                    <td>
                                        <a href="{{ route('projects.production-v2.cutting-plans.show', ['project' => $project->id, 'cuttingPlan' => $plan->id]) }}">
                                            {{ $plan->plan_number }}
                                        </a>
                                        <div class="small text-body-secondary">{{ optional($plan->plan_date)->format('d M Y') ?: '-' }}</div>
                                    </td>
                                    <td>{{ $plan->grade ?: '-' }} / {{ $plan->thickness_mm ?: '-' }} mm</td>
                                    <td class="text-end">{{ number_format($plan->allocations_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No cutting plans released yet.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Recent Part List Updates</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th>Type</th>
                                    <th class="text-end">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($latestParts as $part)
                                <tr>
                                    <td>
                                        <a href="{{ route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $part->id]) }}">
                                            {{ $part->part_code }}
                                        </a>
                                        <div class="small text-body-secondary">{{ $part->part_name }}</div>
                                    </td>
                                    <td>{{ $part->part_type }}</td>
                                    <td class="text-end">{{ number_format((float) $part->required_qty, 3) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No part definitions yet.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Recent Assembly BOM Updates</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Assembly</th>
                                    <th>Status</th>
                                    <th class="text-end">Parts</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($latestAssemblies as $assembly)
                                <tr>
                                    <td>
                                        <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $assembly->id]) }}">
                                            {{ $assembly->assembly_code }}
                                        </a>
                                        <div class="small text-body-secondary">{{ $assembly->assembly_name }}</div>
                                    </td>
                                    <td>{{ $assembly->status }}</td>
                                    <td class="text-end">{{ number_format($assembly->requirements_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No assembly BOM rows yet.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
