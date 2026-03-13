@extends('layouts.erp')

@section('title', 'Production V2 Production Workbench')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Production Workbench</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(auth()->user()?->can('production.plan.view') || auth()->user()?->can('production.plan.create') || auth()->user()?->can('production.plan.update'))
        <a href="{{ route('production-v2.project.design', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-pencil-square me-1"></i>Open Design Module
        </a>
        @endif
        <a href="{{ route('projects.production-v2.dprs.index', ['project' => $project->id]) }}" class="btn btn-sm btn-dark">
            <i class="bi bi-journal-check me-1"></i>Daily DPR
        </a>
        <a href="{{ route('projects.production-v2.route-planning.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-diagram-3 me-1"></i>Route Planning
        </a>
        <a href="{{ route('projects.production-v2.operation-masters.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-sliders me-1"></i>Process Masters
        </a>
        <a href="{{ route('projects.production-v2.cut-batches.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-bounding-box-circles me-1"></i>Cut Batches
        </a>
        <a href="{{ route('projects.production-v2.fitups.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-bezier2 me-1"></i>Fit-ups
        </a>
        <a href="{{ route('projects.production-v2.welding-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-lightning-charge me-1"></i>Welding
        </a>
        <a href="{{ route('projects.production-v2.inspection-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-clipboard2-check me-1"></i>Inspection
        </a>
        <a href="{{ route('projects.production-v2.operation-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-sign-turn-right me-1"></i>Operations
        </a>
        <a href="{{ route('projects.production-v2.rework-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-tools me-1"></i>Rework
        </a>
        <a href="{{ route('projects.production-v2.trial-assemblies.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-rulers me-1"></i>Trial Assembly
        </a>
        <a href="{{ route('projects.production-v2.wip.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-boxes me-1"></i>WIP Pool
        </a>
    </div>
@endsection

@section('content')
    @php $mode = $project->production_mode ?? 'legacy_only'; @endphp

    <div class="alert {{ $mode === 'v2_enabled' ? 'alert-success' : ($mode === 'legacy_to_v2_transition' ? 'alert-warning' : 'alert-secondary') }} d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <div class="fw-semibold">Production-Owned Execution Layer</div>
            <div class="small">
                Design owns part list, assembly BOM, nesting, and material planning. Production owns route planning, process masters, cut execution, WIP, fit-up, welding, inspection, rework, trial assembly, and DPR-driven shopfloor capture.
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
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card h-100 border-danger-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-danger mb-1">Material Shortages</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['shortages']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-warning mb-1">Missing Fit-up</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['missing_fitup']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-warning mb-1">Missing Welding</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['missing_welding']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-warning mb-1">Pending Inspection</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['pending_inspection']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-warning mb-1">QC Gates Pending</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['pending_qc_gates']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-danger-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-danger mb-1">Open Rework</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['open_rework']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-warning-subtle">
                <div class="card-body">
                    <div class="small text-uppercase text-warning mb-1">Part Route Pending</div>
                    <div class="display-6 mb-0">{{ number_format($exceptionSummary['part_route_pending']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Design Parts</div>
                    <div class="display-6 mb-0">{{ number_format($summary['part_definitions']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Design Assemblies</div>
                    <div class="display-6 mb-0">{{ number_format($summary['assemblies']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Design Requirements</div>
                    <div class="display-6 mb-0">{{ number_format($summary['requirements']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Daily DPR</div>
                    <div class="display-6 mb-0">{{ number_format($summary['dprs']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Cut Batches</div>
                    <div class="display-6 mb-0">{{ number_format($summary['cut_batches']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Fit-ups</div>
                    <div class="display-6 mb-0">{{ number_format($summary['fitups']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Welding Events</div>
                    <div class="display-6 mb-0">{{ number_format($summary['welding_events']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Inspection Events</div>
                    <div class="display-6 mb-0">{{ number_format($summary['inspection_events']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Operation Events</div>
                    <div class="display-6 mb-0">{{ number_format($summary['operation_events']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Rework Events</div>
                    <div class="display-6 mb-0">{{ number_format($summary['rework_events']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-uppercase text-body-secondary mb-1">Trial Assemblies</div>
                    <div class="display-6 mb-0">{{ number_format($summary['trial_assemblies']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Shortage Watchlist</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th>Assemblies</th>
                                    <th class="text-end">Required</th>
                                    <th class="text-end">Available</th>
                                    <th class="text-end">Shortage</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($shortageRows as $row)
                                <tr>
                                    <td>
                                        <div>{{ $row['part_definition']?->part_code }}</div>
                                        <div class="small text-body-secondary">{{ $row['part_definition']?->part_name }}</div>
                                    </td>
                                    <td>
                                        @forelse($row['assemblies']->take(3) as $assembly)
                                            <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $assembly->id]) }}" class="badge text-bg-light border text-decoration-none">{{ $assembly->assembly_code }}</a>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                        @if($row['assemblies']->count() > 3)
                                            <div class="small text-body-secondary mt-1">+{{ $row['assemblies']->count() - 3 }} more</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $row['required_qty'], 3) }}</td>
                                    <td class="text-end">{{ number_format((float) $row['available_qty'], 3) }}</td>
                                    <td class="text-end text-danger">{{ number_format((float) $row['shortage_qty'], 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No current pooled-part shortage detected from total assembly demand.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recent Daily DPR</span>
                    <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">Create</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>DPR</th>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Worker</th>
                                    <th class="text-end">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($latestDprs as $row)
                                <tr>
                                    <td>DPR-{{ $row->id }}</td>
                                    <td>{{ $row->dpr_date?->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $row->activity?->name ?: '-' }}</td>
                                    <td>{{ $row->worker?->name ?: '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No daily DPR captured yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Stage Exceptions</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Assembly</th>
                                    <th>Next Route Step</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($missingStageRows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('projects.production-v2.assemblies.show', ['project' => $project->id, 'assembly' => $row['assembly']->id]) }}">
                                            {{ $row['assembly']->assembly_code }}
                                        </a>
                                        <div class="small text-body-secondary">{{ $row['assembly']->assembly_name }}</div>
                                    </td>
                                    <td>
                                        @if(($row['status'] ?? 'operation_pending') === 'qc_gate_pending')
                                            <span class="badge text-bg-warning">{{ $row['step']->operation_name }} QC gate pending</span>
                                            <div class="small text-body-secondary mt-1">
                                                {{ strtoupper(str_replace('_', ' ', $row['step']->qc_gate_mode ?: '-')) }} / {{ strtoupper(str_replace('_', ' ', $row['step']->qc_gate_type ?: '-')) }}
                                            </div>
                                            @if($row['gate_event'])
                                                <div class="small text-body-secondary">Latest: {{ strtoupper($row['gate_event']->result) }} on {{ $row['gate_event']->gate_date?->format('Y-m-d') ?: '-' }}</div>
                                            @endif
                                        @else
                                            <span class="badge text-bg-warning">{{ $row['step']->operation_name }} pending</span>
                                        @endif
                                        <div class="small text-body-secondary mt-1">
                                            {{ number_format((float) $row['completed_qty'], 3) }} / {{ number_format((float) $row['required_qty'], 3) }}
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        @if(($row['status'] ?? 'operation_pending') === 'qc_gate_pending')
                                            <a href="{{ route('projects.production-v2.qc-gates.create', ['project' => $project->id, 'assembly_route_step_id' => $row['step']->id]) }}" class="btn btn-sm btn-outline-primary">QC Gate</a>
                                        @elseif($row['step']->entry_mode === 'specialized' && $row['step']->operation_code === 'fitup')
                                            <a href="{{ route('projects.production-v2.fitups.create', ['project' => $project->id, 'assembly_id' => $row['assembly']->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @elseif($row['step']->entry_mode === 'specialized' && $row['step']->operation_code === 'welding')
                                            <a href="{{ route('projects.production-v2.welding-events.create', ['project' => $project->id, 'assembly_id' => $row['assembly']->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @elseif($row['step']->entry_mode === 'specialized' && $row['step']->operation_code === 'inspection')
                                            <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id, 'assembly_id' => $row['assembly']->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @elseif($row['step']->entry_mode === 'specialized' && $row['step']->operation_code === 'trial_assembly')
                                            <a href="{{ route('projects.production-v2.trial-assemblies.create', ['project' => $project->id, 'assembly_ids' => [$row['assembly']->id]]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @else
                                            <a href="{{ route('projects.production-v2.operation-events.create', ['project' => $project->id, 'operation_master_id' => $row['step']->operation_master_id, 'assembly_id' => $row['assembly']->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No stage gaps detected.</td>
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
                <div class="card-header">Part Route Queue</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Part</th>
                                    <th>Next Route Step</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($partRouteRows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('projects.production-v2.parts.show', ['project' => $project->id, 'part' => $row['part']->id]) }}">
                                            {{ $row['part']->part_code }}
                                        </a>
                                        <div class="small text-body-secondary">{{ $row['part']->part_name }}</div>
                                    </td>
                                    <td>
                                        @if(($row['status'] ?? 'operation_pending') === 'qc_gate_pending')
                                            <span class="badge text-bg-warning">{{ $row['step']->operation_name }} QC gate pending</span>
                                            <div class="small text-body-secondary mt-1">
                                                {{ strtoupper(str_replace('_', ' ', $row['step']->qc_gate_mode ?: '-')) }} / {{ strtoupper(str_replace('_', ' ', $row['step']->qc_gate_type ?: '-')) }}
                                            </div>
                                            @if($row['gate_event'])
                                                <div class="small text-body-secondary">Latest: {{ strtoupper($row['gate_event']->result) }} on {{ $row['gate_event']->gate_date?->format('Y-m-d') ?: '-' }}</div>
                                            @endif
                                        @else
                                            <span class="badge text-bg-warning">{{ $row['step']->operation_name }} pending</span>
                                        @endif
                                        <div class="small text-body-secondary mt-1">
                                            {{ number_format((float) $row['completed_qty'], 3) }} / {{ number_format((float) $row['required_qty'], 3) }}
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        @if(($row['status'] ?? 'operation_pending') === 'qc_gate_pending')
                                            <a href="{{ route('projects.production-v2.qc-gates.create', ['project' => $project->id, 'part_route_step_id' => $row['step']->id]) }}" class="btn btn-sm btn-outline-primary">QC Gate</a>
                                        @elseif($row['step']->entry_mode === 'specialized' && $row['step']->operation_code === 'cutting')
                                            <a href="{{ route('projects.production-v2.cut-batches.create', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @else
                                            <a href="{{ route('projects.production-v2.operation-events.create', ['project' => $project->id, 'operation_master_id' => $row['step']->operation_master_id, 'part_definition_id' => $row['part']->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No pending part route steps detected.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Design Reference: Recent Part Definitions</div>
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
                <div class="card-header">Design Reference: Recent Assemblies</div>
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
                                    <td colspan="3" class="text-center text-muted py-4">No assemblies yet.</td>
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
                <div class="card-header">Open Rework</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rework</th>
                                    <th>Assembly</th>
                                    <th>Result</th>
                                    <th class="text-end">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($openReworks as $row)
                                <tr>
                                    <td>RW-{{ $row->id }}</td>
                                    <td>
                                        <div>{{ $row->assembly?->assembly_code }}</div>
                                        <div class="small text-body-secondary">IN-{{ $row->sourceInspection?->id ?: '-' }}</div>
                                    </td>
                                    <td>{{ $row->final_result ?: '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('projects.production-v2.rework-events.show', ['project' => $project->id, 'reworkEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No open rework events.</td>
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
                <div class="card-header">Inspection Attention</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Inspection</th>
                                    <th>Assembly</th>
                                    <th>Result</th>
                                    <th class="text-end">Open</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($inspectionAttention as $row)
                                <tr>
                                    <td>
                                        IN-{{ $row->id }}
                                        <div class="small text-body-secondary">{{ strtoupper($row->inspection_type) }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $row->assembly?->assembly_code }}</div>
                                        <div class="small text-body-secondary">{{ $row->weldingEvent ? 'WE-' . $row->weldingEvent->id : 'No weld link' }}</div>
                                    </td>
                                    <td>{{ $row->result ?: '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No failed / reoffer / hold inspections right now.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <div class="fw-semibold">Legacy module remains available during transition</div>
                <div class="small text-body-secondary">Use the legacy module for historical or in-flight records only while Design V2 and Production V2 take over new planning and execution.</div>
            </div>
            <a href="{{ route('production.workbench.project', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">
                Open Legacy Module
            </a>
        </div>
    </div>
@endsection
