<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Offer / Quotation {{ $quotation->code }} (Rev {{ $quotation->revision_no }})</title>
    <style>
        /* =========================================================
           OFFER 024 style template (DomPDF friendly)
           - Use tables (no flex)
           - Fixed header/footer in page margins
           ========================================================= */

        @page { margin: 55mm 12mm 28mm 12mm; }

        body {
            font-family: DejaVu Serif, serif;
            font-size: 12px;
            color: #000;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }

        .small { font-size: 10px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }

        /* Header / Footer */
        .header {
            position: fixed;
            top: -55mm;
            left: -12mm;
            right: -12mm;
            height: 55mm;
        }

        .footer {
            position: fixed;
            bottom: -28mm;
            left: -12mm;
            right: -12mm;
            height: 28mm;
        }

        .band-img { width: 100%; height: 12mm; display: block; }

        .header-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .header-inner td { padding: 0; vertical-align: middle; }

        .company-name {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #5B9AD4;
        }

        .works-line {
            font-size: 12px;
            font-weight: 700;
            color: #1F4E79;
            margin-top: 2px;
        }

        .orange-line {
            border-top: 2px solid #ED7C31;
            margin-top: 4px;
        }

        .footer-line {
            border-top: 2px solid #ED7C31;
            margin-bottom: 4px;
        }

        .footer-contact {
            font-size: 11px;
            color: #1F4E79;
            text-align: center;
            line-height: 1.3;
        }
        .footer-contact .linkish { color: #0b5ed7; text-decoration: underline; }

        /* Watermark */
        .watermark {
            position: fixed;
            top: 45%;
            left: 18%;
            width: 64%;
            opacity: 0.06;
            z-index: -1000;
        }
        .watermark img { width: 100%; height: auto; }

        /* Content tables */
        table { width: 100%; border-collapse: collapse; }
        .no-border, .no-border td, .no-border th { border: none !important; }

        .items-table { table-layout: fixed; }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 6px 6px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .items-table th {
            font-weight: 800;
            text-align: center;
        }

        .totals-table td {
            border: 1px solid #000;
            padding: 6px;
            font-weight: 700;
        }

        .prewrap { white-space: pre-wrap; } /* preserve indentation + new lines */

        .subject {
            text-align: center;
            font-weight: 800;
            font-size: 14px;
        }

        .para {
            text-align: justify;
            line-height: 1.5;
        }

        .section-title {
            font-weight: 800;
            margin-bottom: 2px;
        }

        .terms-title {
            font-weight: 800;
            font-size: 14px;
        }

        .terms-block {
            line-height: 1.55;
        }

        .signature-block {
            margin-top: 12px;
        }
    </style>
</head>
<body>

@php
    $isRateOnly = (($quotation->quote_mode ?? 'item') === 'rate_per_kg') && (bool) ($quotation->is_rate_only ?? false);

    // --- Assets (try existing assets first; fall back to shipped template images) ---
    $logoPath = public_path('images/ems-logo.png');
    if (! (is_string($logoPath) && file_exists($logoPath))) {
        $logoPath = public_path('images/quotation_logo.jpeg');
    }
    $logoSrc = (is_string($logoPath) && file_exists($logoPath)) ? $logoPath : null;

    $bandTopPath = public_path('images/quotation_band_top.png');
    $bandTopSrc  = (is_string($bandTopPath) && file_exists($bandTopPath)) ? $bandTopPath : null;

    $bandBottomPath = public_path('images/quotation_band_bottom.png');
    $bandBottomSrc  = (is_string($bandBottomPath) && file_exists($bandBottomPath)) ? $bandBottomPath : null;

    $stampPath = public_path('images/quotation_stamp.jpeg');
    $stampSrc  = (is_string($stampPath) && file_exists($stampPath)) ? $stampPath : null;

    // --- Company line helpers ---
    $companyName = $company?->legal_name ?? $company?->name ?? config('app.name');

    // In the sample it is shown as "Works: <address>"
    $worksLine = trim(implode(', ', array_filter([
        $company?->address_line1 ?? null,
        $company?->address_line2 ?? null,
        $company?->city ?? null,
        $company?->state ?? null,
        $company?->pincode ?? null,
    ])));

    // Footer info (fallback to company fields)
    $email = $company?->email ?? null;
    $phone = $company?->phone ?? null;

    $appUrl = config('app.url');
    $webDisplay = $appUrl ? preg_replace('/^https?:\/\//', '', $appUrl) : null;

    // Terms: quotation override -> selected standard term -> settings fallback
    $termsText = $quotation->terms_text
        ?? optional($quotation->standardTerm)->content
        ?? $terms
        ?? null;

    // Rate header unit (best effort)
    $firstUomCode = optional($quotation->items->first()?->uom)->code ?? 'MT';
@endphp

{{-- Fixed watermark --}}
@if($logoSrc)
    <div class="watermark">
        <img src="{{ $logoSrc }}" alt="watermark">
    </div>
@endif

{{-- Fixed HEADER --}}
<div class="header">
    @if($bandTopSrc)
        <img class="band-img" src="{{ $bandTopSrc }}" alt="band">
    @else
        <div style="height: 12mm; background:#fff;"></div>
    @endif

    <table class="header-inner" style="margin-top: 2mm;">
        <tr>
            <td style="width: 22%; padding-left: 12mm;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="logo" style="height: 30mm; width: auto;">
                @endif
            </td>
            <td style="width: 78%; padding-right: 12mm;" class="text-center">
                <div class="company-name">{{ strtoupper($companyName) }}</div>
                @if($worksLine)
                    <div class="works-line"><span style="font-weight:800;">Works:</span> {{ $worksLine }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="orange-line" style="margin-left: 12mm; margin-right: 12mm;"></div>
</div>

{{-- Fixed FOOTER --}}
<div class="footer">
    <div class="footer-line" style="margin-left: 12mm; margin-right: 12mm;"></div>

    <div class="footer-contact" style="margin-left: 12mm; margin-right: 12mm;">
        @if($email)
            Email: <span class="linkish">{{ $email }}</span>
        @endif
        @if($webDisplay)
            &nbsp;&nbsp;&nbsp; Web: <span class="linkish">{{ $webDisplay }}</span>
        @endif
        @if($phone)
            &nbsp;&nbsp;&nbsp; (M): {{ $phone }}
        @endif
    </div>

    @if($bandBottomSrc)
        <img class="band-img" src="{{ $bandBottomSrc }}" alt="band">
    @else
        <div style="height: 12mm; background:#fff;"></div>
    @endif
</div>

{{-- =========================================================
     PAGE 1: Offer letter + Items
     ========================================================= --}}

<table class="no-border mb-2">
    <tr>
        <td style="width: 70%;" class="fw-bold">
            Ref: {{ $quotation->code }}@if((int)($quotation->revision_no ?? 0) > 0)/REV-{{ $quotation->revision_no }}@endif
        </td>
        <td style="width: 30%;" class="fw-bold text-right">
            Date: {{ optional($quotation->created_at)->format('d-m-Y') }}
        </td>
    </tr>
</table>

<div class="mb-2">
    <div class="fw-bold">To,</div>
    <div class="fw-bold">
        {{ strtoupper($quotation->party?->legal_name ?? $quotation->party?->name ?? '-') }}
    </div>

    @if($quotation->party?->address_line1)
        <div>{{ $quotation->party->address_line1 }}</div>
    @endif
    @if($quotation->party?->address_line2)
        <div>{{ $quotation->party->address_line2 }}</div>
    @endif
    @if($quotation->party?->city || $quotation->party?->state || $quotation->party?->pincode)
        <div>
            {{ trim(implode(', ', array_filter([$quotation->party?->city, $quotation->party?->state]))) }}
            @if($quotation->party?->pincode)
                - {{ $quotation->party->pincode }}
            @endif
        </div>
    @endif
</div>

<div class="subject mb-2">
    Sub: - {{ strtoupper($quotation->project_name ?? 'QUOTATION') }}
    @if(!empty($quotation->project_special_notes))
        <br>{{ strtoupper($quotation->project_special_notes) }}
    @endif
</div>

<div class="para mb-2">
    As Per Your Inquiry by Our Discussion, we are Submitting our Best Offer along with Term and Condition.
    We hope the above is in line with your requirements and we will look forward to an opportunity to serve you.
</div>
<div class="para mb-3">
    Please feel free to contact us for any further clarifications / confirmations that you require in the subject matter.
</div>

{{-- Optional: global scope / exclusions (useful for rate-per-kg offers) --}}
@if(!empty($quotation->scope_of_work))
    <div class="mb-2">
        <div class="section-title">Scope of Work:</div>
        <div class="prewrap">{{ $quotation->scope_of_work }}</div>
    </div>
@endif
@if(!empty($quotation->exclusions))
    <div class="mb-2">
        <div class="section-title">Exclusions:</div>
        <div class="prewrap">{{ $quotation->exclusions }}</div>
    </div>
@endif

{{-- Items table (rate-only vs item-based) --}}
@if($isRateOnly)
    <table class="items-table">
        <thead>
        <tr>
            <th style="width: 7%;">Sr.<br>No</th>
            <th style="width: 58%;">Description</th>
            <th style="width: 10%;">Unit</th>
            <th style="width: 12%;">Qty</th>
            <th style="width: 13%;">Rate<br>Rs. / {{ $firstUomCode }}</th>
        </tr>
        </thead>
        <tbody>
        @php $i = 1; @endphp
        @foreach($quotation->items as $line)
            @php
                $uomCode = $line->uom?->code ?? $firstUomCode;
                $qty = $line->quantity;
            @endphp
            <tr>
                <td class="text-center">{{ $i++ }}</td>
                <td class="prewrap">{{ $line->description }}</td>
                <td class="text-center">
                    {{ $uomCode ? ($uomCode . '.') : '-' }}
                </td>
                <td class="text-center prewrap">
                    {{ ($qty === null || (float)$qty == 0.0) ? 'As Per Sectional Weight' : number_format((float)$qty, 3) }}
                </td>
                <td class="text-center fw-bold">
                    {{ number_format((float)$line->unit_price, 0) }}/-<br>
                    Rs./{{ $uomCode }}.
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <table class="items-table">
        <thead>
        <tr>
            <th style="width: 7%;">Sr.<br>No</th>
            <th style="width: 46%;">Description</th>
            <th style="width: 10%;">Unit</th>
            <th style="width: 10%;">Qty</th>
            <th style="width: 13.5%;">Rate (Rs.)</th>
            <th style="width: 13.5%;">Amount (Rs.)</th>
        </tr>
        </thead>
        <tbody>
        @php $i = 1; @endphp
        @foreach($quotation->items as $line)
            <tr>
                <td class="text-center">{{ $i++ }}</td>
                <td class="prewrap">{{ $line->description }}</td>
                <td class="text-center">{{ $line->uom?->code ?? '-' }}</td>
                <td class="text-right">{{ number_format((float)$line->quantity, 3) }}</td>
                <td class="text-right">{{ number_format((float)$line->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Totals (only for item-based quotation) --}}
    <table class="no-border mt-2">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%;">
                <table class="totals-table">
                    <tr>
                        <td style="width: 60%;">Subtotal</td>
                        <td class="text-right" style="width: 40%;">{{ number_format((float)$quotation->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td class="text-right">{{ number_format((float)$quotation->tax_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Grand Total</td>
                        <td class="text-right">{{ number_format((float)$quotation->grand_total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif

{{-- =========================================================
     PAGE 2: Terms & Condition + Signature
     ========================================================= --}}
<div style="page-break-before: always;"></div>

<div class="terms-title mb-2">[B] TERMS AND CONDITION</div>

@if($termsText)
    <div class="terms-block prewrap">
        {!! nl2br(e($termsText)) !!}
    </div>
@else
    <div class="terms-block prewrap">
1. GST: As applicable extra.
2. Payment Terms: As per mutually agreed schedule.
3. Jurisdiction: Ahmedabad Court Only.
    </div>
@endif

<div class="signature-block mt-3">
    <div>Yours faithfully,</div>
    <div class="fw-bold">For</div>
    <div class="fw-bold">{{ $companyName }}.</div>

    <div style="height: 14mm;"></div>

    @if($stampSrc)
        <div>
            <img src="{{ $stampSrc }}" alt="stamp" style="height: 42mm; width: auto;">
        </div>
    @endif

    <div class="small">Authorised Signatory</div>
</div>

</body>
</html>
