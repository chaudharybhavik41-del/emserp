<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>RFQ {{ $rfq->code }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 14mm 10mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #111827;
        }

        .header {
            width: 100%;
            border: 1px solid #111;
            border-collapse: collapse;
        }
        .header td {
            border: 1px solid #111;
            padding: 6px 8px;
            vertical-align: top;
        }

        .title {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }
        .muted { color: #6b7280; }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .meta td {
            padding: 2px 0;
        }

        .terms {
            width: 100%;
            border: 1px solid #111;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .terms th, .terms td {
            border: 1px solid #111;
            padding: 5px 6px;
            vertical-align: top;
        }
        .terms th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
        }

        table.items {
            width: 100%;
            border: 1px solid #111;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }
        table.items th, table.items td {
            border: 1px solid #111;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        table.items th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
        }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .small { font-size: 9.5px; }

        .footer-note {
            margin-top: 12px;
            font-size: 10px;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <table class="header">
        <tr>
            <td style="width: 65%;">
                <p class="title">Request for Quotation (RFQ)</p>
                <div class="muted small">{{ config('app.name') }}</div>
            </td>
            <td style="width: 35%;">
                <table class="meta">
                    <tr>
                        <td><strong>RFQ No:</strong></td>
                        <td class="text-right">{{ $rfq->code }}</td>
                    </tr>
                    <tr>
                        <td><strong>RFQ Date:</strong></td>
                        <td class="text-right">{{ optional($rfq->rfq_date)?->format('d-m-Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Due Date:</strong></td>
                        <td class="text-right">{{ optional($rfq->due_date)?->format('d-m-Y') ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table class="meta">
                    <tr>
                        <td style="width: 17%;"><strong>Project:</strong></td>
                        <td style="width: 33%;">
                            @if($rfq->project)
                                {{ $rfq->project->code }} - {{ $rfq->project->name }}
                            @else
                                General / Store
                            @endif
                        </td>
                        <td style="width: 17%;"><strong>Department:</strong></td>
                        <td style="width: 33%;">{{ $rfq->department?->name ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Buyer Terms --}}
    <table class="terms">
        <thead>
            <tr>
                <th colspan="6">Buyer Terms</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 16%;"><strong>Payment</strong></td>
                <td style="width: 17%;">{{ $rfq->payment_terms_days !== null ? ($rfq->payment_terms_days.' days') : '-' }}</td>
                <td style="width: 16%;"><strong>Delivery</strong></td>
                <td style="width: 17%;">{{ $rfq->delivery_terms_days !== null ? ($rfq->delivery_terms_days.' days') : '-' }}</td>
                <td style="width: 16%;"><strong>Freight</strong></td>
                <td style="width: 18%;">{{ $rfq->freight_terms ?? '-' }}</td>
            </tr>
            @if(!empty($rfq->remarks))
                <tr>
                    <td><strong>Remarks</strong></td>
                    <td colspan="5">{{ $rfq->remarks }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 28%;">Item</th>
                <th style="width: 10%;">Brand</th>
                <th style="width: 10%;">Grade</th>
                <th style="width: 23%;">Specs</th>
                <th style="width: 10%;" class="text-right">Qty</th>
                <th style="width: 7%;" class="text-center">UOM</th>
                <th style="width: 7%;">Desc</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rfq->items as $item)
            <tr>
                <td class="text-center">{{ $item->line_no }}</td>
                <td>
                    {{ optional($item->item)->name }}
                    @if(optional($item->item)->code)
                        <div class="muted small">{{ $item->item->code }}</div>
                    @endif
                </td>
                <td>{{ $item->brand ?: '' }}</td>
                <td>{{ $item->grade ?: '' }}</td>
                <td class="small">
                    @php
                        $dims = [];
                        if ($item->thickness_mm) $dims[] = 'T '.$item->thickness_mm.'mm';
                        if ($item->width_mm) $dims[] = 'W '.$item->width_mm.'mm';
                        if ($item->length_mm) $dims[] = 'L '.$item->length_mm.'mm';
                    @endphp

                    @if(!empty($dims))
                        {{ implode('  ', $dims) }}
                    @else
                        -
                    @endif

                    @if($item->weight_per_meter_kg)
                        <div>Wt/m: {{ number_format((float)$item->weight_per_meter_kg, 3) }}</div>
                    @endif
                    @if($item->qty_pcs !== null)
                        <div>Pcs: {{ number_format((float)$item->qty_pcs, 3) }}</div>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float)$item->quantity, 3) }}</td>
                <td class="text-center">{{ $item->uom?->code ?? $item->uom?->name ?? '' }}</td>
                <td class="small">{{ $item->description ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="footer-note muted">
        Kindly submit your quotation by due date mentioning RFQ No. <strong>{{ $rfq->code }}</strong>.
        Please quote as per specified Brand / Grade / Specs / UOM.
    </div>

    <div style="margin-top:10px;">
        Regards,<br>
        {{ optional($rfq->creator)->name ?? '' }}<br>
        (Purchase)
    </div>

</body>
</html>
