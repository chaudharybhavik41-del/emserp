@extends('layouts.erp')

@section('title', 'Production V2 Rework Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">RW-{{ $reworkEvent->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.rework-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.rework-events.print', ['project' => $project->id, 'reworkEvent' => $reworkEvent->id]) }}" class="btn btn-sm btn-outline-dark" target="_blank">Print</a>
        <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id, 'assembly_id' => $reworkEvent->assembly_id, 'welding_event_id' => $reworkEvent->sourceInspection?->related_welding_event_id, 'source_rework_event_id' => $reworkEvent->id]) }}" class="btn btn-sm btn-primary">Create Re-Inspection</a>
        <a href="{{ route('projects.production-v2.trial-assemblies.create', ['project' => $project->id, 'assembly_ids' => [$reworkEvent->assembly_id]]) }}" class="btn btn-sm btn-outline-primary">Create Trial Assembly</a>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Rework Date</div><div>{{ $reworkEvent->rework_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Assembly</div><div>{{ $reworkEvent->assembly?->assembly_code ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Reason Code</div><div>{{ $reworkEvent->reason_code ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Re-offer Date</div><div>{{ $reworkEvent->reoffer_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Final Result</div><div>{{ $reworkEvent->final_result ?: '-' }}</div></div>
                <div class="col-12 col-md-3">
                    <div class="small text-body-secondary">Daily DPR</div>
                    <div>
                        @if($reworkEvent->reworkDpr)
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $reworkEvent->reworkDpr->id]) }}">DPR-{{ $reworkEvent->reworkDpr->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12"><div class="small text-body-secondary">Reason Description</div><div>{{ $reworkEvent->reason_description ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Action Taken</div><div>{{ $reworkEvent->action_taken ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $reworkEvent->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Source Inspection</div>
        <div class="card-body">
            @if($reworkEvent->sourceInspection)
                <div class="row g-3">
                    <div class="col-12 col-md-2"><div class="small text-body-secondary">Inspection Ref</div><div>IN-{{ $reworkEvent->sourceInspection->id }}</div></div>
                    <div class="col-12 col-md-2"><div class="small text-body-secondary">Type</div><div>{{ strtoupper($reworkEvent->sourceInspection->inspection_type) }}</div></div>
                    <div class="col-12 col-md-2"><div class="small text-body-secondary">Result</div><div>{{ $reworkEvent->sourceInspection->result ?: '-' }}</div></div>
                    <div class="col-12 col-md-3"><div class="small text-body-secondary">Checked By</div><div>{{ $reworkEvent->sourceInspection->checkedBy?->name ?: '-' }}</div></div>
                    <div class="col-12 col-md-3"><div class="small text-body-secondary">Weld Ref</div><div>{{ $reworkEvent->sourceInspection->weldingEvent ? ('WE-' . $reworkEvent->sourceInspection->weldingEvent->id) : '-' }}</div></div>
                    <div class="col-12"><div class="small text-body-secondary">Defect Description</div><div>{{ $reworkEvent->sourceInspection->defect_description ?: '-' }}</div></div>
                </div>
            @else
                <div class="text-muted">No source inspection linked.</div>
            @endif
        </div>
    </div>
@endsection
