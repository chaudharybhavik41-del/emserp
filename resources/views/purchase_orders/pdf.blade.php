<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $order->code }}</title>
    <style>
        /**
         * Purchase Order PDF (A4) – Professional layout optimized for DomPDF
         * - No ERP sidebar/layout chrome
         * - Fixed table widths to prevent overflow/cutouts
         * - Auto smaller font when item count increases
         * - Long text wraps safely (even without spaces)
         */

        @page { size: A4 portrait; margin: 12mm 12mm 16mm 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* Helpers */
        .w-100 { width: 100%; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #444; }
        .small { font-size: 10px; }
        .xsmall { font-size: 9px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-0 { margin-bottom: 0; }

        /* Base tables */
        table { width: 100%; border-collapse: collapse; }
        th, td { vertical-align: top; }

        .tbl th, .tbl td {
            border: 1px solid #000;
            padding: 4px 5px;
        }
        .tbl th {
            background: #f0f0f0;
            font-weight: 700;
        }

        .no-border, .no-border td, .no-border th { border: none !important; }

        /* Header */
        .doc-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.4px;
            margin: 0;
            padding: 0;
            text-transform: uppercase;
        }

        .doc-subtitle {
            font-size: 10px;
            color: #333;
            margin-top: 2px;
        }

        .hr {
            border-top: 1px solid #000;
            margin: 8px 0 10px;
        }

        /* Info boxes */
        .box {
            border: 1px solid #000;
            padding: 6px 7px;
        }
        .box-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 4px;
        }
        .box p { margin: 0; }

        /* Items table */
        .items-table {
            table-layout: fixed; /* critical to prevent overflow */
        }

        .items-table thead { display: table-header-group; }
        .items-table tfoot { display: table-row-group; }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 3px 4px;
        }

        .items-table th {
            background: #f0f0f0;
            font-weight: 700;
        }

        /* Safer wrapping for long strings */
        .wrap {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /* Keep a row from splitting weirdly (best effort; very tall rows may still split) */
        .items-table tr { page-break-inside: avoid; }

        .item-name { font-weight: 700; }
        .specs { font-size: 9px; color: #444; margin-top: 2px; }

        /* Font scaling by item count */
        .items-sm th, .items-sm td { font-size: 10px; }
        .items-xs th, .items-xs td { font-size: 9px;  padding: 2px 3px; }
        .items-xxs th, .items-xxs td { font-size: 8px; padding: 2px 2px; }

        /* Totals */
        .totals-wrap { margin-top: 8px; }
        .totals-table { width: 55%; margin-left: auto; }
        .totals-table td { padding: 4px 6px; }
        .totals-table .label { font-weight: 700; }
        .totals-table .value { text-align: right; }
        .grand { font-size: 12px; font-weight: 800; }

        /* Footer note */
        .footer-note {
            font-size: 9px;
            color: #333;
            margin-top: 10px;
        }
    </style>
</head>
<body>

@php
    // Company / Vendor blocks
    $company = class_exists(\App\Models\Company::class)
        ? \App\Models\Company::where('is_default', 1)->first()
        : null;

    $vendor = $order->vendor ?? null;

    // Logo (safe fallback to a known public path)
    $logoPath = public_path('images/ems-logo.png');
    $logoSrc  = (is_string($logoPath) && file_exists($logoPath)) ? $logoPath : null;

    $companyName = $company?->legal_name ?? $company?->name ?? config('app.name');

    $companyCityLine = trim(implode(', ', array_filter([
        $company?->city ?? null,
        $company?->state ?? null,
        $company?->pincode ?? null,
    ])));

    $companyGstin = $company?->gst_number ?? $company?->gstin ?? null;
    $companyPan   = $company?->pan_number ?? $company?->pan ?? null;

    $vendorName = $vendor?->legal_name ?? $vendor?->name ?? '-';

    $vendorLines = array_filter([
        $vendor?->address_line1 ?? null,
        $vendor?->address_line2 ?? null,
    ]);

    $vendorCityLine = trim(implode(', ', array_filter([
        $vendor?->city ?? null,
        $vendor?->state ?? null,
        $vendor?->pincode ?? null,
        $vendor?->country ?? null,
    ])));

    $vendorGstin  = $vendor?->gstin ?? $vendor?->gst_number ?? null;
    $vendorPan    = $vendor?->pan ?? null;

    $vPhone = $vendor?->primary_phone ?? $vendor?->phone ?? null;
    $vEmail = $vendor?->primary_email ?? $vendor?->email ?? null;

    // Items
    $items = collect($order->items ?? []);
    $itemCount = $items->count();

    // Auto shrink font for large item counts
    $itemsFontClass = $itemCount > 28 ? 'items-xxs' : ($itemCount > 16 ? 'items-xs' : 'items-sm');

    // Optional columns
    $showDisc = $items->contains(fn ($it) => (float) ($it->discount_percent ?? 0) > 0.0001);

    // GST amounts (prefer stored columns; fallback to net-amount difference)
    $hasCgst = $items->contains(fn ($it) => (float) ($it->cgst_amount ?? 0) > 0.0001);
    $hasSgst = $items->contains(fn ($it) => (float) ($it->sgst_amount ?? 0) > 0.0001);
    $hasIgst = $items->contains(fn ($it) => (float) ($it->igst_amount ?? 0) > 0.0001);

    // In normal GST logic you should NOT have IGST together with CGST/SGST, but if it happens,
    // we still show a single combined GST column to keep the table within A4 width.
    $showGst = $hasCgst || $hasSgst || $hasIgst || $items->contains(fn ($it) => (float) ($it->tax_percent ?? 0) > 0.0001);

    $subTotal = (float) $items->sum(fn($it) => (float) ($it->amount ?? $it->basic_amount ?? 0));
    $cgstTotal = (float) $items->sum('cgst_amount');
    $sgstTotal = (float) $items->sum('sgst_amount');
    $igstTotal = (float) $items->sum('igst_amount');

    // Net total
    $netTotalFromLines = (float) $items->sum(fn($it) => (float) ($it->net_amount ?? $it->total_amount ?? 0));
    $grandTotal = (float) ($order->total_amount ?? ($netTotalFromLines > 0 ? $netTotalFromLines : 0));
@endphp

{{-- Page numbers (DomPDF) --}}
<script type="text/php">
if (isset($pdf)) {
    $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
    $pdf->page_text(500, 820, $text, $font, 9, array(0,0,0));
}
</script>

{{-- Header --}}
<table class="no-border w-100">
    <tr>
        <td style="width: 65%; vertical-align: top;">
            <table class="no-border w-100">
                <tr>
                    @if($logoSrc)
                        <td style="width: 60px; padding-right: 8px;">
                            <img src="{{ $logoSrc }}" alt="{{ $companyName }}" style="height: 42px; width: auto;">
                        </td>
                    @endif
                    <td style="vertical-align: top;">
                        <div style="font-weight: 800; font-size: 14px;">{{ $companyName }}</div>
                        @if(!empty($company?->address_line1))
                            <div class="small">{{ $company?->address_line1 }}</div>
                        @endif
                        @if(!empty($company?->address_line2))
                            <div class="small">{{ $company?->address_line2 }}</div>
                        @endif
                        @if($companyCityLine !== '')
                            <div class="small">{{ $companyCityLine }}</div>
                        @endif
                        <div class="small">
                            @if(!empty($company?->phone)) Phone: {{ $company?->phone }} @endif
                            @if(!empty($company?->email)) @if(!empty($company?->phone)) &nbsp;|&nbsp; @endif Email: {{ $company?->email }} @endif
                        </div>
                        <div class="small">
                            @if(!empty($companyGstin)) GSTIN: {{ $companyGstin }} @endif
                            @if(!empty($companyPan)) @if(!empty($companyGstin)) &nbsp;|&nbsp; @endif PAN: {{ $companyPan }} @endif
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 35%; vertical-align: top;" class="text-right">
            <div class="doc-title">Purchase Order</div>
            <div class="doc-subtitle">PO No: <strong>{{ $order->code }}</strong></div>
            <div class="doc-subtitle">PO Date: {{ optional($order->po_date)?->format('d-m-Y') ?? '-' }}</div>
            @if($order->project)
                <div class="doc-subtitle">Project: {{ $order->project->code }} - {{ $order->project->name }}</div>
            @endif
            <div class="doc-subtitle">Status: {{ ucfirst($order->status) }}</div>
        </td>
    </tr>
</table>

<div class="hr"></div>

{{-- Vendor + PO details boxes --}}
<table class="no-border w-100">
    <tr>
        <td style="width: 50%; padding-right: 6px; vertical-align: top;">
            <div class="box">
                <div class="box-title">Vendor / Supplier</div>
                <p><strong>{{ $vendorName }}</strong></p>

                @if(!empty($vendorLines))
                    @foreach($vendorLines as $line)
                        <p class="small">{{ $line }}</p>
                    @endforeach
                @endif
                @if($vendorCityLine !== '')
                    <p class="small">{{ $vendorCityLine }}</p>
                @endif

                @if(!empty($vPhone) || !empty($vEmail))
                    <p class="small mt-1">
                        @if(!empty($vPhone)) Phone: {{ $vPhone }} @endif
                        @if(!empty($vEmail)) @if(!empty($vPhone)) &nbsp;|&nbsp; @endif Email: {{ $vEmail }} @endif
                    </p>
                @endif

                @if(!empty($vendorGstin) || !empty($vendorPan))
                    <p class="small">
                        @if(!empty($vendorGstin)) GSTIN: {{ $vendorGstin }} @endif
                        @if(!empty($vendorPan)) @if(!empty($vendorGstin)) &nbsp;|&nbsp; @endif PAN: {{ $vendorPan }} @endif
                    </p>
                @endif
            </div>
        </td>

        <td style="width: 50%; padding-left: 6px; vertical-align: top;">
            <div class="box">
                <div class="box-title">PO Details</div>

                <table class="no-border w-100">
                    <tr>
                        <td class="small muted" style="width: 45%;">Expected Delivery</td>
                        <td class="small" style="width: 55%;">{{ optional($order->expected_delivery_date)?->format('d-m-Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Department</td>
                        <td class="small">{{ optional($order->department)->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Payment Terms (days)</td>
                        <td class="small">{{ $order->payment_terms_days ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Delivery Terms (days)</td>
                        <td class="small">{{ $order->delivery_terms_days ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="small muted">Freight Terms</td>
                        <td class="small">{{ $order->freight_terms ?? '-' }}</td>
                    </tr>
                    @if($order->rfq)
                        <tr>
                            <td class="small muted">RFQ Ref</td>
                            <td class="small">{{ $order->rfq->code ?? '' }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- Items --}}
<div class="mt-2" style="font-weight: 700; text-transform: uppercase; font-size: 11px;">Items</div>

<table class="items-table {{ $itemsFontClass }} w-100" style="margin-top: 4px;">
    <thead>
    <tr>
        <th style="width: 8mm;">#</th>
        <th class="wrap">Description</th>

        <th style="width: 16mm;" class="text-right">Qty</th>
        <th style="width: 11mm;" class="text-center">UOM</th>
        <th style="width: 18mm;" class="text-right">Rate</th>

        @if($showDisc)
            <th style="width: 12mm;" class="text-right">Disc%</th>
        @endif

        @if($showGst)
            <th style="width: 20mm;" class="text-right">GST</th>
        @endif

        <th style="width: 20mm;" class="text-right">Amount</th>
        <th style="width: 20mm;" class="text-right">Net</th>
    </tr>
    </thead>
    <tbody>
    @foreach($items as $index => $item)
        @php
            $taxPct = (float) ($item->tax_percent ?? $item->tax_rate ?? 0);

            $uomText = $item->uom?->code
                ?? $item->uom?->name
                ?? optional(optional($item->item)->uom)->code
                ?? optional(optional($item->item)->uom)->name
                ?? '';

            $itemName = optional($item->item)->name ?: ($item->description ?: '-');

            // Compact specs (keep columns fewer; specs show as second line)
            $specParts = [];

            if (filled($item->brand ?? null)) {
                $specParts[] = 'Brand: ' . $item->brand;
            }
            if (filled($item->grade ?? null)) {
                $specParts[] = 'Grade: ' . $item->grade;
            }
            if ((float) ($item->length_mm ?? 0) > 0) {
                $specParts[] = 'L: ' . rtrim(rtrim(number_format((float) $item->length_mm, 2), '0'), '.') . 'mm';
            }
            if ((float) ($item->width_mm ?? 0) > 0) {
                $specParts[] = 'W: ' . rtrim(rtrim(number_format((float) $item->width_mm, 2), '0'), '.') . 'mm';
            }
            if ((float) ($item->thickness_mm ?? 0) > 0) {
                $specParts[] = 'T: ' . rtrim(rtrim(number_format((float) $item->thickness_mm, 2), '0'), '.') . 'mm';
            }
            if ((float) ($item->weight_per_meter_kg ?? 0) > 0) {
                $specParts[] = 'Wt/m: ' . rtrim(rtrim(number_format((float) $item->weight_per_meter_kg, 3), '0'), '.') . 'kg';
            }
            if ((float) ($item->qty_pcs ?? 0) > 0) {
                $specParts[] = 'Qty(pcs): ' . rtrim(rtrim(number_format((float) $item->qty_pcs, 3), '0'), '.');
            }
            if (filled($item->section_profile ?? null)) {
                $specParts[] = 'Section: ' . $item->section_profile;
            }
            if ($showDisc && (float) ($item->discount_percent ?? 0) > 0.0001) {
                $specParts[] = 'Disc: ' . number_format((float) $item->discount_percent, 2) . '%';
            }

            // GST amount (prefer explicit columns; fallback to net-amount difference)
            $gstAmt = (float) ($item->cgst_amount ?? 0) + (float) ($item->sgst_amount ?? 0) + (float) ($item->igst_amount ?? 0);
            $amt = (float) ($item->amount ?? $item->basic_amount ?? 0);
            $net = (float) ($item->net_amount ?? $item->total_amount ?? 0);
            if ($gstAmt <= 0.0001 && $net > 0 && $amt > 0) {
                $gstAmt = max(0, $net - $amt);
            }
        @endphp

        <tr>
            <td class="text-center">{{ $index + 1 }}</td>

            <td class="wrap">
                <div class="item-name">{{ $itemName }}</div>

                @if(!empty($item->description) && $itemName !== $item->description)
                    <div class="specs wrap">{{ $item->description }}</div>
                @endif

                @if(count($specParts))
                    <div class="specs wrap">{{ implode(' | ', $specParts) }}</div>
                @endif
            </td>

            <td class="text-right">{{ number_format((float) ($item->quantity ?? 0), 3) }}</td>
            <td class="text-center">{{ $uomText }}</td>
            <td class="text-right">{{ number_format((float) ($item->rate ?? 0), 2) }}</td>

            @if($showDisc)
                <td class="text-right">
                    {{ number_format((float) ($item->discount_percent ?? 0), 2) }}
                </td>
            @endif

            @if($showGst)
                <td class="text-right">
                    @if($taxPct > 0.0001 || $gstAmt > 0.0001)
                        {{ $taxPct > 0.0001 ? number_format($taxPct, 2) . '%' : '-' }}
                        <div class="xsmall">{{ number_format($gstAmt, 2) }}</div>
                    @else
                        -
                    @endif
                </td>
            @endif

            <td class="text-right">{{ number_format($amt, 2) }}</td>
            <td class="text-right">{{ number_format($net, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- Totals --}}
<div class="totals-wrap">
    <table class="tbl totals-table">
        <tbody>
        <tr>
            <td class="label">Sub Total</td>
            <td class="value">{{ number_format($subTotal, 2) }}</td>
        </tr>

        @if($showGst)
            @if($igstTotal > 0.0001)
                <tr>
                    <td class="label">IGST</td>
                    <td class="value">{{ number_format($igstTotal, 2) }}</td>
                </tr>
            @else
                @if($cgstTotal > 0.0001)
                    <tr>
                        <td class="label">CGST</td>
                        <td class="value">{{ number_format($cgstTotal, 2) }}</td>
                    </tr>
                @endif
                @if($sgstTotal > 0.0001)
                    <tr>
                        <td class="label">SGST</td>
                        <td class="value">{{ number_format($sgstTotal, 2) }}</td>
                    </tr>
                @endif
            @endif
        @endif

        <tr>
            <td class="label grand">Grand Total</td>
            <td class="value grand">{{ number_format($grandTotal, 2) }}</td>
        </tr>
        </tbody>
    </table>
</div>

{{-- Remarks --}}
@if(!empty($order->remarks))
    <div class="mt-2" style="font-weight:700; text-transform: uppercase; font-size: 11px;">Remarks</div>
    <div class="small wrap">{{ $order->remarks }}</div>
@endif

{{-- Terms & Conditions --}}
<div class="mt-2" style="font-weight:700; text-transform: uppercase; font-size: 11px;">Terms &amp; Conditions</div>
@if(!empty($order->terms_text))
    <div class="small wrap">{!! nl2br(e($order->terms_text)) !!}</div>
@else
    <ol class="small" style="padding-left: 18px; margin: 0;">
        <li>Prices are firm and not subject to escalation unless otherwise agreed in writing.</li>
        <li>Material must strictly conform to the specification, grade and sizes mentioned in this Purchase Order.</li>
        <li>Any deviation or change must be approved in writing by the purchaser before supply.</li>
        <li>Delivery shall be made on or before the expected delivery date. Any delay is subject to rejection of material and/or liquidated damages as per purchaser policy.</li>
        <li>All invoices must clearly mention PO number, item description, quantity and applicable taxes.</li>
        <li>Payment will be made as per the agreed payment terms, subject to receipt and acceptance of material and all required documents.</li>
        <li>Goods are subject to inspection at our site. Rejected material shall be taken back by the supplier at their own cost.</li>
        <li>Risk and ownership of goods shall pass to the purchaser only after receipt and acceptance at the designated delivery location.</li>
        <li>Any disputes arising out of this Purchase Order shall be subject to the jurisdiction of the purchaser's registered office location, unless otherwise specified.</li>
        <li>This Purchase Order, along with any referenced documents, constitutes the entire agreement between purchaser and supplier for the described goods/services.</li>
    </ol>
@endif

{{-- Signatures --}}
<table class="no-border w-100 mt-3">
    <tr>
        <td style="width: 33%; padding-right: 8px; vertical-align: top;">
            <div style="font-weight: 700;">Prepared By</div>
            <div style="height: 32px;"></div>
            <div class="small muted">{{ optional($order->creator)->name ?? '' }}</div>
        </td>
        <td style="width: 34%; padding: 0 8px; vertical-align: top;">
            <div style="font-weight: 700;">Authorized By</div>
            <div style="height: 32px;"></div>
            <div class="small muted">
                {{ optional($order->approver)->name ?? optional($order->approvedBy)->name ?? '' }}
            </div>
        </td>
        <td style="width: 33%; padding-left: 8px; vertical-align: top;">
            <div style="font-weight: 700;">Supplier Acceptance</div>
            <div style="height: 32px;"></div>
            <div class="small muted">Signature &amp; Stamp</div>
        </td>
    </tr>
</table>

<div class="footer-note">
    This is a system generated Purchase Order. Please reference <strong>{{ $order->code }}</strong> in all invoices and correspondence.
</div>

</body>
</html>
