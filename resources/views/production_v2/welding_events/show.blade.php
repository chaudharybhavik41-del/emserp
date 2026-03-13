@extends('layouts.erp')

@section('title', 'Production V2 Welding Event')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">WE-{{ $weldingEvent->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.welding-events.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.welding-events.print', ['project' => $project->id, 'weldingEvent' => $weldingEvent->id]) }}" class="btn btn-sm btn-outline-dark" target="_blank">Print</a>
        @if($latestFitup)
            <a href="{{ route('projects.production-v2.fitups.show', ['project' => $project->id, 'fitup' => $latestFitup->id]) }}" class="btn btn-sm btn-outline-primary">Open Latest Fit-up</a>
        @endif
        <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id, 'assembly_id' => $weldingEvent->assembly_id, 'welding_event_id' => $weldingEvent->id]) }}" class="btn btn-sm btn-primary">Create Inspection</a>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Date</div><div>{{ $weldingEvent->weld_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Assembly</div><div>{{ $weldingEvent->assembly?->assembly_code ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Process</div><div>{{ $weldingEvent->welding_process }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Welder</div><div>{{ $weldingEvent->welder?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-1"><div class="small text-body-secondary">Status</div><div>{{ $weldingEvent->status }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Line No</div><div>{{ $weldingEvent->line_no ?: '-' }}</div></div>
                <div class="col-12 col-md-2">
                    <div class="small text-body-secondary">Daily DPR</div>
                    <div>
                        @if($weldingEvent->dpr)
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $weldingEvent->dpr->id]) }}">DPR-{{ $weldingEvent->dpr->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Joint</div><div>{{ $weldingEvent->joint_description ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Weld Size</div><div>{{ $weldingEvent->weld_size_mm ? number_format((float) $weldingEvent->weld_size_mm, 3) . ' mm' : '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">WPS Ref</div><div>{{ $weldingEvent->wpss_ref ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Consumable</div><div>{{ $weldingEvent->consumableItem?->code ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Machine</div><div>{{ $weldingEvent->machine?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-12"><div class="small text-body-secondary">Remarks</div><div>{{ $weldingEvent->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Execution Chain</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Assembly</div>
                    <div>{{ $weldingEvent->assembly?->assembly_code ?: '-' }}</div>
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
                    <div class="small text-body-secondary">Inspection Count</div>
                    <div>{{ number_format($weldingEvent->inspections->count()) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($latestFitup)
        <div class="card mb-3">
            <div class="card-header">Latest Fit-up Context</div>
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

    <div class="card">
        <div class="card-header">Related Inspections</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Inspection</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Result</th>
                            <th>Checked By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($weldingEvent->inspections as $inspection)
                        <tr>
                            <td>IN-{{ $inspection->id }}</td>
                            <td>{{ $inspection->inspection_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ strtoupper($inspection->inspection_type) }}</td>
                            <td>{{ $inspection->result ?: '-' }}</td>
                            <td>{{ $inspection->checkedBy?->name ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $inspection->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No inspection events linked to this welding event.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
