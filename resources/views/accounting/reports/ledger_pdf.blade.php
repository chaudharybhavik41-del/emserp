<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ledger Statement</title>
    <style>
        @page { margin: 22px 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        .header {
            width: 100%;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-cell {
            width: 90px;
        }
        .logo {
            max-width: 72px;
            max-height: 54px;
        }
        .company-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px 0;
        }
        .report-title {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 6px 0;
        }
        .meta {
            font-size: 10px;
            color: #4b5563;
            line-height: 1.5;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        table.report th,
        table.report td {
            border: 1px solid #d1d5db;
            padding: 6px 7px;
        }
        table.report th {
            background: #f3f4f6;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .particulars {
            width: 42%;
        }
        .summary-row td {
            font-weight: 700;
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="{{ $companyName }}" class="logo">
                    @endif
                </td>
                <td>
                    <div class="company-name">{{ $companyName }}</div>
                    <div class="report-title">Ledger Statement</div>
                    <div class="meta">
                        <div>Account: {{ trim(($account->code ? ($account->code . ' - ') : '') . $account->name) }}</div>
                        <div>Period: {{ optional($fromDate)->toDateString() }} to {{ optional($toDate)->toDateString() }}</div>
                        @if(!empty($company?->gst_number))
                            <div>GSTIN: {{ $company->gst_number }}</div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th class="particulars">Particulars</th>
                <th style="width: 11%;">Vch Type</th>
                <th style="width: 15%;">Vch No.</th>
                <th style="width: 10%;" class="text-right">Debit (INR)</th>
                <th style="width: 10%;" class="text-right">Credit (INR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr @class(['summary-row' => in_array($row['particulars'] ?? '', ['OPENING BALANCE', 'CLOSING BALANCE'], true)])>
                    <td>{{ $row['date'] ?: '' }}</td>
                    <td>{{ $row['particulars'] ?: '' }}</td>
                    <td class="text-center">{{ $row['voucher_type'] ?: '' }}</td>
                    <td>{{ $row['voucher_no'] ?: '' }}</td>
                    <td class="text-right">{{ $row['debit'] ?: '' }}</td>
                    <td class="text-right">{{ $row['credit'] ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
