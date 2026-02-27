<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Report' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        /* A4 setup */
        @page {
            size: A4 portrait;
            margin: 12mm 10mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .rpt-page {
            width: 100%;
        }

        .rpt-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .rpt-title {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }

        .rpt-meta {
            font-size: 10px;
            color: #6b7280;
            margin-top: 3px;
        }

        .rpt-badges {
            margin-top: 6px;
            font-size: 10px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            margin-right: 6px;
        }

        .rpt-summary {
            margin: 8px 0 10px;
            display: table;
            width: 100%;
        }

        .rpt-summary .box {
            display: table-cell;
            width: 25%;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            vertical-align: top;
        }

        .rpt-summary .label {
            color: #6b7280;
            font-size: 10px;
        }

        .rpt-summary .value {
            font-weight: 700;
            font-size: 12px;
        }

        /* Table */
        table.rpt-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* ensures A4 fit */
        }

        table.rpt-table th,
        table.rpt-table td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            vertical-align: top;
            word-break: break-word;
        }

        table.rpt-table th {
            background: #f3f4f6;
            font-weight: 700;
        }

        /* Repeat header per browser + PDF */
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }

        /* Auto-compact based on column count */
        .rpt-cols-8 table.rpt-table { font-size: 10.5px; }
        .rpt-cols-9 table.rpt-table { font-size: 10px; }
        .rpt-cols-10 table.rpt-table { font-size: 9.5px; }
        .rpt-cols-11 table.rpt-table,
        .rpt-cols-12 table.rpt-table { font-size: 9px; }

        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #6b7280; }

        /* Print-only helpers */
        .no-print { display: none; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="rpt-page">
    @yield('content')
</div>
</body>
</html>
