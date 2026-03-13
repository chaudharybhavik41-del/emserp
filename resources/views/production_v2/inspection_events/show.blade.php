@extends('layouts.erp')

@section('title', 'Production V2 Inspection Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">IN-{{ $inspectionEvent->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.inspection-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.inspection-events.print', ['project' => $project->id, 'inspectionEvent' => $inspectionEvent->id]) }}" class="btn btn-sm btn-outline-dark" target="_blank">Print</a>
        @if($latestFitup)
            <a href="{{ route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $latestFitup->id]) }}" class="btn btn-sm btn-outline-primary">Open Latest Fit-up</a>
        @endif
        @if($inspectionEvent->weldingEvent)
            <a href="{{ route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $inspectionEvent->weldingEvent->id]) }}" class="btn btn-sm btn-outline-primary">Open Welding Event</a>
        @endif
        @if(in_array($inspectionEvent->result, ['failed', 'reoffer', 'hold'], true))
            <a href="{{ route('projects.production-v2.rework-events.create', ['project' => $project->id, 'inspection_event_id' => $inspectionEvent->id]) }}" class="btn btn-sm btn-primary">Create Rework Event</a>
        @endif
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Date</div><div>{{ $inspectionEvent->inspection_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Assembly</div><div>{{ $inspectionEvent->assembly?->assembly_code ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Type</div><div>{{ strtoupper($inspectionEvent->inspection_type) }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Result</div><div>{{ $inspectionEvent->result ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Checked By</div><div>{{ $inspectionEvent->checkedBy?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Weld Ref</div><div>{{ $inspectionEvent->weldingEvent ? ('WE-' . $inspectionEvent->weldingEvent->id) : '-' }}</div></div>
                <div class="col-12 col-md-3">
                    <div class="small text-body-secondary">Daily DPR</div>
                    <div>
                        @if($inspectionEvent->relatedDpr)
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $inspectionEvent->relatedDpr->id]) }}">DPR-{{ $inspectionEvent->relatedDpr->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Welder</div><div>{{ $inspectionEvent->weldingEvent?->welder?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Line No</div><div>{{ $inspectionEvent->line_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Inspector Agency</div><div>{{ $inspectionEvent->inspector_agency ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Defect Type</div><div>{{ $inspectionEvent->defect_type ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Reoffer No</div><div>{{ $inspectionEvent->reoffer_no ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Retest Result</div><div>{{ $inspectionEvent->retest_result ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Defect Description</div><div>{{ $inspectionEvent->defect_description ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Repair Action</div><div>{{ $inspectionEvent->repair_action ?: '-' }}</div></div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $inspectionEvent->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Execution Chain</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Assembly</div>
                    <div>{{ $inspectionEvent->assembly?->assembly_code ?: '-' }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Latest Fit-up</div>
                    <div>
                        @if($latestFitup)
                            <a href="{{ route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $latestFitup->id]) }}">FU-{{ $latestFitup->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Linked Weld</div>
                    <div>
                        @if($inspectionEvent->weldingEvent)
                            <a href="{{ route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $inspectionEvent->weldingEvent->id]) }}">WE-{{ $inspectionEvent->weldingEvent->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($latestFitup)
        <div class="card mb-3">
            <div class="card-header">Latest Fit-up Traceability</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Part</th>
                                <th>WIP Ref</th>
                                <th class="text-end">Qty</th>
                                <th>Source Ref</th>
                                <th>Heat</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($latestFitup->consumptions as $row)
                            @php
                                $sourceRef = $row->plate_number_snapshot
                                    ?: ($row->wipItem?->motherStock?->section_profile
                                        ?: ($row->wipItem?->piece_no ?: $row->wipItem?->lot_no));
                            @endphp
                            <tr>
                                <td>{{ $row->partDefinition?->part_code }}<div class="small text-body-secondary">{{ $row->partDefinition?->part_name }}</div></td>
                                <td>{{ $row->wipItem?->piece_no ?: ($row->wipItem?->lot_no ?: '-') }}</td>
                                <td class="text-end">{{ number_format((float) $row->consumed_qty, 3) }} {{ $row->uom?->code }}</td>
                                <td>{{ $sourceRef ?: '-' }}</td>
                                <td>{{ $row->heat_number_snapshot ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($inspectionEvent->reworkEvents->isNotEmpty())
        <div class="card">
            <div class="card-header">Related Rework Events</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Rework</th>
                                <th>Date</th>
                                <th>Result</th>
                                <th>Action</th>
                                <th class="text-end">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($inspectionEvent->reworkEvents as $rework)
                            <tr>
                                <td>RW-{{ $rework->id }}</td>
                                <td>{{ $rework->rework_date?->format('Y-m-d') ?: '-' }}</td>
                                <td>{{ $rework->final_result ?: '-' }}</td>
                                <td>{{ $rework->action_taken ?: '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('projects.production-v2.rework-events.show', ['project' => $project->id, 'reworkEvent' => $rework->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
