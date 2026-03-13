@extends('layouts.erp')

@section('title', 'Production V2 Fit-up')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">FU-{{ $fitup->id }}</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.fitups.index', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Back</a>
        <a href="{{ route('projects.production-v2.fitups.print', ['project' => $project->id, 'fitup' => $fitup->id]) }}" class="btn btn-sm btn-outline-dark" target="_blank">Print</a>
        <a href="{{ route('projects.production-v2.welding-events.create', ['project' => $project->id, 'assembly_id' => $fitup->assembly_id]) }}" class="btn btn-sm btn-primary">Create Welding Event</a>
        @if($latestWeldingEvent)
            <a href="{{ route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $latestWeldingEvent->id]) }}" class="btn btn-sm btn-outline-primary">Open Latest Welding</a>
        @endif
        @if($latestInspectionEvent)
            <a href="{{ route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $latestInspectionEvent->id]) }}" class="btn btn-sm btn-outline-primary">Open Latest Inspection</a>
        @endif
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Date</div><div>{{ $fitup->fitup_date?->format('Y-m-d') ?: '-' }}</div></div>
                <div class="col-12 col-md-3"><div class="small text-body-secondary">Assembly</div><div>{{ $fitup->assembly?->assembly_code ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Supervisor</div><div>{{ $fitup->supervisor?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Inspector</div><div>{{ $fitup->inspector?->name ?: '-' }}</div></div>
                <div class="col-12 col-md-1"><div class="small text-body-secondary">Shift</div><div>{{ $fitup->shift ?: '-' }}</div></div>
                <div class="col-12 col-md-2"><div class="small text-body-secondary">Status</div><div>{{ $fitup->status }}</div></div>
                <div class="col-12 col-md-2">
                    <div class="small text-body-secondary">Daily DPR</div>
                    <div>
                        @if($fitup->dpr)
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $fitup->dpr->id]) }}">DPR-{{ $fitup->dpr->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12"><div class="small text-body-secondary">Remarks</div><div>{{ $fitup->remarks ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Execution Chain</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Assembly</div>
                    <div>{{ $fitup->assembly?->assembly_code ?: '-' }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Latest Welding</div>
                    <div>
                        @if($latestWeldingEvent)
                            <a href="{{ route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $latestWeldingEvent->id]) }}">WE-{{ $latestWeldingEvent->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="small text-body-secondary">Latest Inspection</div>
                    <div>
                        @if($latestInspectionEvent)
                            <a href="{{ route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $latestInspectionEvent->id]) }}">IN-{{ $latestInspectionEvent->id }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Consumed WIP</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Part</th>
                            <th>WIP Ref</th>
                            <th class="text-end">Qty</th>
                            <th>Observed</th>
                            <th>Specified</th>
                            <th>Source Ref</th>
                            <th>Heat</th>
                            <th>Dim OK</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($fitup->consumptions as $row)
                        @php
                            $sourceRef = $row->plate_number_snapshot
                                ?: ($row->wipItem?->motherStock?->section_profile
                                    ?: ($row->wipItem?->piece_no ?: $row->wipItem?->lot_no));
                        @endphp
                        <tr>
                            <td>{{ $row->partDefinition?->part_code }}<div class="small text-body-secondary">{{ $row->partDefinition?->part_name }}</div></td>
                            <td>{{ $row->wipItem?->piece_no ?: ($row->wipItem?->lot_no ?: '-') }}</td>
                            <td class="text-end">{{ number_format((float) $row->consumed_qty, 3) }} {{ $row->uom?->code }}</td>
                            <td>{{ $row->observed_dimension_text ?: '-' }}</td>
                            <td>{{ $row->specified_dimension_text ?: '-' }}</td>
                            <td>{{ $sourceRef ?: '-' }}</td>
                            <td>{{ $row->heat_number_snapshot ?: '-' }}</td>
                            <td>{{ is_null($row->dimension_ok) ? '-' : ($row->dimension_ok ? 'Yes' : 'No') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No WIP consumption rows found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
