<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Inspection Event IN-{{ $inspectionEvent->id }}</title>
    @include('production_v2.partials.print_styles')
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">Inspection Sheet</div>
            <div class="subtitle">{{ $project->code }} - {{ $project->name }} | IN-{{ $inspectionEvent->id }}</div>
        </div>
        <div><button type="button" onclick="window.print()">Print</button></div>
    </div>
    <div class="sheet">
        <div class="grid">
            <div class="card"><div class="label">Assembly</div><div class="value">{{ $inspectionEvent->assembly?->assembly_code ?: '-' }}</div></div>
            <div class="card"><div class="label">Inspection Date</div><div class="value">{{ $inspectionEvent->inspection_date?->format('Y-m-d') ?: '-' }}</div></div>
            <div class="card"><div class="label">Type</div><div class="value">{{ strtoupper($inspectionEvent->inspection_type) }}</div></div>
            <div class="card"><div class="label">Result</div><div class="value">{{ strtoupper($inspectionEvent->result ?: '-') }}</div></div>
        </div>

        <div class="section">
            <h2>Inspection Context</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Linked Weld</th>
                        <td>{{ $inspectionEvent->weldingEvent ? 'WE-' . $inspectionEvent->weldingEvent->id : '-' }}</td>
                        <th>Welder</th>
                        <td>{{ $inspectionEvent->weldingEvent?->welder?->name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Checked By</th>
                        <td>{{ $inspectionEvent->checkedBy?->name ?: '-' }}</td>
                        <th>Inspector Agency</th>
                        <td>{{ $inspectionEvent->inspector_agency ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Line No</th>
                        <td>{{ $inspectionEvent->line_no ?: '-' }}</td>
                        <th>Reoffer No</th>
                        <td>{{ $inspectionEvent->reoffer_no ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Defect Type</th>
                        <td>{{ $inspectionEvent->defect_type ?: '-' }}</td>
                        <th>Retest Result</th>
                        <td>{{ $inspectionEvent->retest_result ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Defect Description</th>
                        <td colspan="3">{{ $inspectionEvent->defect_description ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Repair Action</th>
                        <td colspan="3">{{ $inspectionEvent->repair_action ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Remarks</th>
                        <td colspan="3">{{ $inspectionEvent->remarks ?: '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($latestFitup)
            <div class="section">
                <h2>Latest Fit-up Traceability</h2>
                <table>
                    <thead>
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
                                <td>{{ $row->partDefinition?->part_code }}<div class="muted">{{ $row->partDefinition?->part_name }}</div></td>
                                <td>{{ $row->wipItem?->piece_no ?: ($row->wipItem?->lot_no ?: '-') }}</td>
                                <td class="text-end">{{ number_format((float) $row->consumed_qty, 3) }} {{ $row->uom?->code }}</td>
                                <td>{{ $sourceRef ?: '-' }}</td>
                                <td>{{ $row->heat_number_snapshot ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($inspectionEvent->reworkEvents->isNotEmpty())
            <div class="section">
                <h2>Related Rework</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Rework</th>
                            <th>Date</th>
                            <th>Result</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inspectionEvent->reworkEvents as $rework)
                            <tr>
                                <td>RW-{{ $rework->id }}</td>
                                <td>{{ $rework->rework_date?->format('Y-m-d') ?: '-' }}</td>
                                <td>{{ strtoupper($rework->final_result ?: '-') }}</td>
                                <td>{{ $rework->action_taken ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>
