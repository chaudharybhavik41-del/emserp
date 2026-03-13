<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Fit-up FU-{{ $fitup->id }}</title>
    @include('production_v2.partials.print_styles')
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">Fit-up Execution Sheet</div>
            <div class="subtitle">{{ $project->code }} - {{ $project->name }} | FU-{{ $fitup->id }}</div>
        </div>
        <div>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </div>
    <div class="sheet">
        <div class="grid">
            <div class="card"><div class="label">Assembly</div><div class="value">{{ $fitup->assembly?->assembly_code ?: '-' }}</div><div class="muted">{{ $fitup->assembly?->assembly_name ?: '-' }}</div></div>
            <div class="card"><div class="label">Fit-up Date</div><div class="value">{{ $fitup->fitup_date?->format('Y-m-d') ?: '-' }}</div></div>
            <div class="card"><div class="label">Supervisor</div><div class="value">{{ $fitup->supervisor?->name ?: '-' }}</div></div>
            <div class="card"><div class="label">Inspector</div><div class="value">{{ $fitup->inspector?->name ?: '-' }}</div></div>
        </div>

        <div class="section">
            <h2>Execution Chain</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Latest Welding</th>
                        <td>{{ $latestWeldingEvent ? 'WE-' . $latestWeldingEvent->id : '-' }}</td>
                        <th>Latest Inspection</th>
                        <td>{{ $latestInspectionEvent ? 'IN-' . $latestInspectionEvent->id : '-' }}</td>
                    </tr>
                    <tr>
                        <th>Shift</th>
                        <td>{{ $fitup->shift ?: '-' }}</td>
                        <th>Status</th>
                        <td>{{ strtoupper($fitup->status) }}</td>
                    </tr>
                    <tr>
                        <th>Remarks</th>
                        <td colspan="3">{{ $fitup->remarks ?: '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>Consumed WIP</h2>
            <table>
                <thead>
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
                            <td>{{ $row->partDefinition?->part_code }}<div class="muted">{{ $row->partDefinition?->part_name }}</div></td>
                            <td>{{ $row->wipItem?->piece_no ?: ($row->wipItem?->lot_no ?: '-') }}</td>
                            <td class="text-end">{{ number_format((float) $row->consumed_qty, 3) }} {{ $row->uom?->code }}</td>
                            <td>{{ $row->observed_dimension_text ?: '-' }}</td>
                            <td>{{ $row->specified_dimension_text ?: '-' }}</td>
                            <td>{{ $sourceRef ?: '-' }}</td>
                            <td>{{ $row->heat_number_snapshot ?: '-' }}</td>
                            <td>{{ is_null($row->dimension_ok) ? '-' : ($row->dimension_ok ? 'YES' : 'NO') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">No WIP consumption rows found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
