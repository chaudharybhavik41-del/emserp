<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Welding Event WE-{{ $weldingEvent->id }}</title>
    @include('production_v2.partials.print_styles')
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">Welding Execution Sheet</div>
            <div class="subtitle">{{ $project->code }} - {{ $project->name }} | WE-{{ $weldingEvent->id }}</div>
        </div>
        <div><button type="button" onclick="window.print()">Print</button></div>
    </div>
    <div class="sheet">
        <div class="grid">
            <div class="card"><div class="label">Assembly</div><div class="value">{{ $weldingEvent->assembly?->assembly_code ?: '-' }}</div><div class="muted">{{ $weldingEvent->assembly?->assembly_name ?: '-' }}</div></div>
            <div class="card"><div class="label">Weld Date</div><div class="value">{{ $weldingEvent->weld_date?->format('Y-m-d') ?: '-' }}</div></div>
            <div class="card"><div class="label">Process</div><div class="value">{{ $weldingEvent->welding_process }}</div></div>
            <div class="card"><div class="label">Welder</div><div class="value">{{ $weldingEvent->welder?->name ?: '-' }}</div></div>
        </div>

        <div class="section">
            <h2>Welding Parameters</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Joint Description</th>
                        <td>{{ $weldingEvent->joint_description ?: '-' }}</td>
                        <th>Line No</th>
                        <td>{{ $weldingEvent->line_no ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Weld Size</th>
                        <td>{{ $weldingEvent->weld_size_mm ? number_format((float) $weldingEvent->weld_size_mm, 3) . ' mm' : '-' }}</td>
                        <th>WPS / PQR Ref</th>
                        <td>{{ $weldingEvent->wpss_ref ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Consumable</th>
                        <td>{{ $weldingEvent->consumableItem?->code ?: '-' }} {{ $weldingEvent->consumable_batch ? '/ ' . $weldingEvent->consumable_batch : '' }}</td>
                        <th>Machine</th>
                        <td>{{ $weldingEvent->machine?->name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Supervisor</th>
                        <td>{{ $weldingEvent->supervisor?->name ?: '-' }}</td>
                        <th>Inspector</th>
                        <td>{{ $weldingEvent->inspector?->name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Remarks</th>
                        <td colspan="3">{{ $weldingEvent->remarks ?: '-' }}</td>
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

        <div class="section">
            <h2>Related Inspections</h2>
            <table>
                <thead>
                    <tr>
                        <th>Inspection</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Result</th>
                        <th>Checked By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($weldingEvent->inspections as $inspection)
                        <tr>
                            <td>IN-{{ $inspection->id }}</td>
                            <td>{{ $inspection->inspection_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ strtoupper($inspection->inspection_type) }}</td>
                            <td>{{ strtoupper($inspection->result ?: '-') }}</td>
                            <td>{{ $inspection->checkedBy?->name ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No inspection events linked to this welding event.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
