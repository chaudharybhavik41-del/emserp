<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Rework Event RW-{{ $reworkEvent->id }}</title>
    @include('production_v2.partials.print_styles')
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">Rework Sheet</div>
            <div class="subtitle">{{ $project->code }} - {{ $project->name }} | RW-{{ $reworkEvent->id }}</div>
        </div>
        <div><button type="button" onclick="window.print()">Print</button></div>
    </div>
    <div class="sheet">
        <div class="grid">
            <div class="card"><div class="label">Assembly</div><div class="value">{{ $reworkEvent->assembly?->assembly_code ?: '-' }}</div></div>
            <div class="card"><div class="label">Rework Date</div><div class="value">{{ $reworkEvent->rework_date?->format('Y-m-d') ?: '-' }}</div></div>
            <div class="card"><div class="label">Reason Code</div><div class="value">{{ strtoupper($reworkEvent->reason_code ?: '-') }}</div></div>
            <div class="card"><div class="label">Final Result</div><div class="value">{{ strtoupper($reworkEvent->final_result ?: '-') }}</div></div>
        </div>

        <div class="section">
            <h2>Rework Context</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Latest Fit-up</th>
                        <td>{{ $latestFitup ? 'FU-' . $latestFitup->id : '-' }}</td>
                        <th>Latest Welding</th>
                        <td>{{ $latestWeldingEvent ? 'WE-' . $latestWeldingEvent->id : '-' }}</td>
                    </tr>
                    <tr>
                        <th>Re-offer Date</th>
                        <td>{{ $reworkEvent->reoffer_date?->format('Y-m-d') ?: '-' }}</td>
                        <th>Remarks</th>
                        <td>{{ $reworkEvent->remarks ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Reason Description</th>
                        <td colspan="3">{{ $reworkEvent->reason_description ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Action Taken</th>
                        <td colspan="3">{{ $reworkEvent->action_taken ?: '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Source Inspection</h2>
            <table>
                <tbody>
                    @if($reworkEvent->sourceInspection)
                        <tr>
                            <th>Inspection Ref</th>
                            <td>IN-{{ $reworkEvent->sourceInspection->id }}</td>
                            <th>Type</th>
                            <td>{{ strtoupper($reworkEvent->sourceInspection->inspection_type) }}</td>
                        </tr>
                        <tr>
                            <th>Inspection Result</th>
                            <td>{{ strtoupper($reworkEvent->sourceInspection->result ?: '-') }}</td>
                            <th>Checked By</th>
                            <td>{{ $reworkEvent->sourceInspection->checkedBy?->name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Linked Weld</th>
                            <td>{{ $reworkEvent->sourceInspection->weldingEvent ? 'WE-' . $reworkEvent->sourceInspection->weldingEvent->id : '-' }}</td>
                            <th>Welder</th>
                            <td>{{ $reworkEvent->sourceInspection->weldingEvent?->welder?->name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Defect Description</th>
                            <td colspan="3">{{ $reworkEvent->sourceInspection->defect_description ?: '-' }}</td>
                        </tr>
                    @else
                        <tr><td colspan="4" class="text-center">No source inspection linked.</td></tr>
                    @endif
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
    </div>
</body>
</html>
