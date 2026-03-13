@extends('layouts.erp')

@section('title', 'Production V2 DPR')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">DPR-{{ $productionDpr->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.dprs.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route($nextRoute, $nextRouteParameters) }}" class="btn btn-sm btn-primary">Open Linked Stage</a>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Date</div><div>{{ $productionDpr->dpr_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Activity</div><div>{{ $productionDpr->activity?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-1"><div class="small text-body-secondary">Shift</div><div>{{ $productionDpr->shift ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Worker</div><div>{{ $productionDpr->worker?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Machine</div><div>{{ $productionDpr->machine?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Status</div><div>{{ ucfirst($productionDpr->status) }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $productionDpr->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Linked Execution Records</div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between"><span>Cut Batches</span><span>{{ $links['cut_batches']->count() }}</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span>Fit-ups</span><span>{{ $links['fitups']->count() }}</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span>Welding Events</span><span>{{ $links['welding_events']->count() }}</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span>Inspection Events</span><span>{{ $links['inspection_events']->count() }}</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span>Operation Events</span><span>{{ $links['operation_events']->count() }}</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span>Rework Events</span><span>{{ $links['rework_events']->count() }}</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span>Trial Assemblies</span><span>{{ $links['trial_assemblies']->count() }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">Recent Linked Rows</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Ref</th>
                                    <th>Assembly</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $rows = collect()
                                        ->merge($links['cut_batches']->map(fn ($row) => ['type' => 'Cut Batch', 'ref' => 'CB-' . $row->id, 'assembly' => '-', 'url' => route('projects.production-v2.cut-batches.show', ['project' => $project->id, 'cutBatch' => $row->id])]))
                                        ->merge($links['fitups']->map(fn ($row) => ['type' => 'Fit-up', 'ref' => 'FU-' . $row->id, 'assembly' => $row->assembly?->assembly_code ?: '-', 'url' => route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $row->id])]))
                                        ->merge($links['welding_events']->map(fn ($row) => ['type' => 'Welding', 'ref' => 'WE-' . $row->id, 'assembly' => $row->assembly?->assembly_code ?: '-', 'url' => route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $row->id])]))
                                        ->merge($links['inspection_events']->map(fn ($row) => ['type' => 'Inspection', 'ref' => 'IN-' . $row->id, 'assembly' => $row->assembly?->assembly_code ?: '-', 'url' => route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $row->id])]))
                                        ->merge($links['operation_events']->map(fn ($row) => ['type' => $row->operationMaster?->name ?: 'Operation', 'ref' => 'OP-' . $row->id, 'assembly' => $row->assembly?->assembly_code ?: $row->partDefinition?->part_code ?: '-', 'url' => route('projects.production-v2.operation-events.show', ['project' => $project->id, 'operationEvent' => $row->id])]))
                                        ->merge($links['rework_events']->map(fn ($row) => ['type' => 'Rework', 'ref' => 'RW-' . $row->id, 'assembly' => $row->assembly?->assembly_code ?: '-', 'url' => route('projects.production-v2.rework-events.show', ['project' => $project->id, 'reworkEvent' => $row->id])]))
                                        ->merge($links['trial_assemblies']->map(fn ($row) => ['type' => 'Trial Assembly', 'ref' => 'TA-' . $row->id, 'assembly' => $row->assembly_group_ref ?: '-', 'url' => route('projects.production-v2.trial-assemblies.show', ['project' => $project->id, 'trialAssembly' => $row->id])]))
                                        ->take(12);
                                @endphp
                                @forelse($rows as $row)
                                    <tr>
                                        <td>{{ $row['type'] }}</td>
                                        <td><a href="{{ $row['url'] }}">{{ $row['ref'] }}</a></td>
                                        <td>{{ $row['assembly'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">No linked execution records yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
