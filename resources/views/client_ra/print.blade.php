<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client RA Bill - {{ $clientRa->ra_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .no-print { margin-bottom: 10px; }
        @media print { .no-print { display: none; } }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 16px; font-weight: bold; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
        th { background: #f3f3f3; }
        .text-end { text-align: right; }
        .totals { width: 45%; margin-left: auto; }
        .totals td { border: none; padding: 3px 0; }
        .totals .label { text-align: right; padding-right: 10px; }
        .totals .value { text-align: right; font-weight: bold; }
        .sign { margin-top: 30px; display: flex; justify-content: space-between; }
        .sign .box { width: 30%; text-align: center; border-top: 1px solid #ccc; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="header">
        <div>
            <div class="title">{{ config('app.name', 'EMS Infra') }}</div>
            <div class="muted">Client RA Bill / Sales Invoice</div>
        </div>
        <div class="text-end">
            <div><strong>RA No:</strong> {{ $clientRa->ra_number }}</div>
            <div><strong>Date:</strong> {{ $clientRa->bill_date?->format('d-m-Y') }}</div>
            <div><strong>Project:</strong> {{ $clientRa->project?->name }}</div>
        </div>
    </div>

    <table style="margin-bottom: 12px;">
        <tr>
            <td style="width: 50%">
                <div><strong>Bill To (Client)</strong></div>
                <div>{{ $clientRa->client?->name }}</div>
                <div class="muted">{{ $clientRa->client?->address ?? '' }}</div>
            </td>
            <td style="width: 50%">
                <div><strong>Contract / PO</strong></div>
                <div><strong>Contract No:</strong> {{ $clientRa->contract_number ?? '-' }}</div>
                <div><strong>PO No:</strong> {{ $clientRa->po_number ?? '-' }}</div>
                <div><strong>Period:</strong> {{ $clientRa->period_from?->format('d-m-Y') ?? '-' }} to {{ $clientRa->period_to?->format('d-m-Y') ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 10%">BOQ</th>
                <th>Description</th>
                <th style="width: 8%">UOM</th>
                <th style="width: 10%" class="text-end">Qty</th>
                <th style="width: 10%" class="text-end">Rate</th>
                <th style="width: 12%" class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clientRa->lines as $i => $line)
                <tr>
                    <td class="text-end">{{ $i + 1 }}</td>
                    <td>{{ $line->boq_item_code ?? '-' }}</td>
                    <td>
                        <div><strong>{{ $line->description }}</strong></div>
                        @if($line->remarks)
                            <div class="muted">{{ $line->remarks }}</div>
                        @endif
                        @if($line->sac_hsn_code)
                            <div class="muted">HSN/SAC: {{ $line->sac_hsn_code }}</div>
                        @endif
                    </td>
                    <td>{{ $line->uom?->name ?? '-' }}</td>
                    <td class="text-end">{{ number_format((float) $line->current_qty, 4) }}</td>
                    <td class="text-end">{{ number_format((float) $line->rate, 2) }}</td>
                    <td class="text-end">{{ number_format((float) $line->current_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 10px;">
        <tr>
            <td class="label">Current Amount</td>
            <td class="value">{{ number_format((float) $clientRa->current_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Less: Retention</td>
            <td class="value">{{ number_format((float) $clientRa->retention_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Less: Other Deductions</td>
            <td class="value">{{ number_format((float) $clientRa->other_deductions, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Net Amount</td>
            <td class="value">{{ number_format((float) $clientRa->net_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Add: GST</td>
            <td class="value">{{ number_format((float) $clientRa->total_gst, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Invoice Total</td>
            <td class="value">{{ number_format((float) $clientRa->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Less: TDS @ {{ rtrim(rtrim(number_format((float) $clientRa->tds_rate, 4), '0'), '.') }}% {{ $clientRa->tds_section ? '(' . $clientRa->tds_section . ')' : '' }}</td>
            <td class="value">{{ number_format((float) $clientRa->tds_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label"><strong>Net Receivable</strong></td>
            <td class="value"><strong>{{ number_format((float) $clientRa->receivable_amount, 2) }}</strong></td>
        </tr>
    </table>

    <div style="margin-top: 12px;">
        <div><strong>Remarks:</strong> {{ $clientRa->remarks ?? '-' }}</div>
    </div>

    <div class="sign">
        <div class="box">Prepared By</div>
        <div class="box">Checked By</div>
        <div class="box">Authorised Signatory</div>
    </div>
</body>
</html>
