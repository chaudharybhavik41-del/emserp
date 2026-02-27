<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gate Pass {{ $gatePass->gatepass_number }}</title>
    <style>
        @page { margin: 15mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }

        .text-right   { text-align: right; }
        .text-center  { text-align: center; }
        .text-left    { text-align: left; }
        .small        { font-size: 9px; }
        .fw-bold      { font-weight: bold; }
        .mt-1         { margin-top: 4px; }
        .mt-2         { margin-top: 8px; }
        .mt-3         { margin-top: 12px; }
        .mb-1         { margin-bottom: 4px; }
        .mb-2         { margin-bottom: 8px; }
        .mb-3         { margin-bottom: 12px; }
        .border       { border: 1px solid #000; }
        .border-bottom{ border-bottom: 1px solid #000; }
        .border-top   { border-top: 1px solid #000; }
        .border-left  { border-left: 1px solid #000; }
        .border-right { border-right: 1px solid #000; }
        .w-100        { width: 100%; }
        .table        { border-collapse: collapse; width: 100%; }
        .table th,
        .table td     { border: 1px solid #000; padding: 3px 4px; }

        .no-border    { border: 0 !important; }

        .heading      { font-size: 16px; font-weight: bold; text-decoration: underline; }

        .col-50       { width: 50%; }
    </style>
</head>
<body>

    {{-- HEADER: COMPANY + GATEPASS INFO --}}
    <table class="w-100">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if($company ?? null)
                    <div class="fw-bold" style="font-size: 14px;">
                        {{ $company->legal_name ?? $company->name }}
                    </div>
                    @if($company->address_line1)
                        <div>{{ $company->address_line1 }}</div>
                    @endif
                    @if($company->address_line2)
                        <div>{{ $company->address_line2 }}</div>
                    @endif
                    <div>
                        {{ $company->city }}, {{ $company->state }} - {{ $company->pincode }}
                    </div>
                    <div class="small">
                        Phone: {{ $company->phone }}
                        @if($company->email)
                            &nbsp;|&nbsp; Email: {{ $company->email }}
                        @endif
                    </div>
                    @if($company->gst_number)
                        <div class="small">GSTIN: {{ $company->gst_number }}</div>
                    @endif
                    @if($company->pan_number)
                        <div class="small">PAN: {{ $company->pan_number }}</div>
                    @endif
                @endif
            </td>
            <td style="width: 40%; vertical-align: top;" class="text-right">
                <div class="heading">GATE PASS</div>
                <div class="mb-1">
                    No: <strong>{{ $gatePass->gatepass_number }}</strong>
                </div>
                <div class="mb-1">
                    Date:
                    <strong>{{ optional($gatePass->gatepass_date)->format('d-m-Y') }}</strong>
                </div>
                @if($gatePass->gatepass_time)
                    <div class="mb-1">
                        Time:
                        <strong>{{ \Illuminate\Support\Carbon::parse($gatePass->gatepass_time)->format('H:i') }}</strong>
                    </div>
                @endif
                <div class="mb-1">
                    Type: <strong>{{ $gatePass->type_label }}</strong>
                </div>
                <div class="mb-1">
                    Status: <strong>{{ $gatePass->status_label }}</strong>
                </div>
            </td>
        </tr>
    </table>

    {{-- PROJECT / PARTY / VEHICLE BLOCK --}}
    <table class="w-100 mt-2" style="border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td class="border-right" style="width: 50%; vertical-align: top; padding: 4px;">
                <div class="fw-bold small">Project</div>
                @if($gatePass->project)
                    <div>{{ $gatePass->project->code }} - {{ $gatePass->project->name }}</div>
                @else
                    <div>General / Store / Outside Work</div>
                @endif

                <div class="fw-bold small mt-2">Address</div>
                <div>
                    {{ $gatePass->address
                        ?: (optional($gatePass->project)->site_location ?: '-') }}
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding: 4px;">
                <div class="fw-bold small">Contractor</div>
                <div>
                    @if($gatePass->contractor)
                        {{ $gatePass->contractor->name }}
                    @else
                        -
                    @endif
                </div>

                <div class="fw-bold small mt-1">To Party / Vendor</div>
                <div>
                    @if($gatePass->toParty)
                        {{ $gatePass->toParty->name }}
                    @else
                        -
                    @endif
                </div>

                <div class="fw-bold small mt-1">Vehicle No.</div>
                <div>{{ $gatePass->vehicle_number ?? '-' }}</div>

                <div class="fw-bold small mt-1">Driver Name</div>
                <div>{{ $gatePass->driver_name ?? '-' }}</div>

                <div class="fw-bold small mt-1">Transport Mode</div>
                <div>{{ $gatePass->transport_mode ?? '-' }}</div>
            </td>
        </tr>
    </table>

    {{-- GENERAL REMARKS --}}
    <table class="w-100 mt-2" style="border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td style="padding: 4px;">
                <div class="fw-bold small">Reason / Purpose</div>
                <div>{{ $gatePass->reason ?? '-' }}</div>

                <div class="fw-bold small mt-1">Remarks</div>
                <div>{{ $gatePass->remarks ?? '-' }}</div>
            </td>
        </tr>
    </table>

    {{-- LINE ITEMS --}}
    <div class="mt-3 fw-bold">Gate Pass Items</div>

    <table class="table mt-1">
        <thead>
        <tr>
            <th style="width: 5%;">Sr</th>
            <th style="width: 30%;">Item / Machine</th>
            <th style="width: 18%;">Description</th>
            <th style="width: 10%;" class="text-right">Qty</th>
            <th style="width: 8%;">UOM</th>
            <th style="width: 10%;">Returnable</th>
            <th style="width: 12%;">Expected Return</th>
            <th style="width: 7%;" class="text-right">Ret. Qty</th>
        </tr>
        </thead>
        <tbody>
        @forelse($gatePass->lines as $line)
            <tr>
                <td class="text-center">{{ $line->line_no }}</td>
                <td>
                    @if($gatePass->type === 'project_material')
                        @if($line->item)
                            {{ $line->item->code }} - {{ $line->item->name }}
                        @else
                            Item #{{ $line->item_id }}
                        @endif
                    @elseif($gatePass->type === 'machinery_maintenance')
                        @if($line->machine)
                            {{ $line->machine->code }} - {{ $line->machine->name }}
                        @else
                            Machine #{{ $line->machine_id }}
                        @endif
                    @endif
                </td>
                <td>
                    @if($line->description)
                        {{ $line->description }}
                    @else
                        {{ $line->remarks }}
                    @endif
                </td>
                <td class="text-right">
                    {{ number_format((float) $line->qty, 3) }}
                </td>
                <td class="text-center">
                    @if($line->uom)
                        {{ $line->uom->name }}
                    @elseif($gatePass->type === 'machinery_maintenance')
                        Nos.
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">
                    {{ $line->is_returnable ? 'Yes' : 'No' }}
                </td>
                <td class="text-center">
                    {{ optional($line->expected_return_date)->format('d-m-Y') ?? '-' }}
                </td>
                <td class="text-right">
                    {{ number_format((float) ($line->returned_qty ?? 0), 3) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center">No lines found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    {{-- SIGNATURES --}}
    <table class="w-100 mt-3">
        <tr>
            <td style="width: 33%; vertical-align: top;">
                <div class="fw-bold small">Prepared By</div>
                <div class="mt-3">&nbsp;</div>
                <div class="border-top small" style="padding-top: 2px;">
                    {{ optional($gatePass->createdBy)->name ?: '________________' }}
                </div>
            </td>
            <td style="width: 33%; vertical-align: top;" class="text-center">
                <div class="fw-bold small">Store / Gate Incharge</div>
                <div class="mt-3">&nbsp;</div>
                <div class="border-top small" style="padding-top: 2px;">
                    Signature
                </div>
            </td>
            <td style="width: 33%; vertical-align: top;" class="text-right">
                <div class="fw-bold small">Received By</div>
                <div class="mt-3">&nbsp;</div>
                <div class="border-top small" style="padding-top: 2px;">
                    Name &amp; Signature
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
