@php
    /**
     * Email view for Daily Digest.
     *
     * Expects:
     * - $digest: array (from DailyDigestService)
     */
    $d = $digest;
    $date = $d['date'] ?? '';
    $fmtMoney = function ($v) {
        if ($v === null) return '0.00';
        return number_format((float) $v, 2);
    };
    $fmtInt = function ($v) {
        return number_format((int) ($v ?? 0));
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily ERP Digest</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; padding: 0; background: #f6f7f9; }
        .container { max-width: 860px; margin: 0 auto; padding: 18px; }
        .card { background: #ffffff; border: 1px solid #e6e8ee; border-radius: 8px; overflow: hidden; }
        .header { padding: 16px 18px; background: #0d6efd; color: #fff; }
        .header h1 { margin: 0; font-size: 18px; }
        .header .meta { margin-top: 6px; font-size: 12px; opacity: 0.9; }
        .section { padding: 16px 18px; border-top: 1px solid #eef0f4; }
        .section h2 { margin: 0 0 10px 0; font-size: 15px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th, .grid td { padding: 8px 10px; border: 1px solid #eef0f4; font-size: 13px; text-align: left; }
        .grid th { background: #fafbfc; font-weight: 700; }
        .kpi { display: inline-block; margin: 4px 14px 4px 0; font-size: 13px; }
        .kpi b { font-size: 14px; }
        .muted { color: #6b7280; }
        .footer { padding: 12px 18px; font-size: 12px; color: #6b7280; border-top: 1px solid #eef0f4; }
        .badge { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #3730a3; }
        .two-col { width: 100%; border-collapse: collapse; }
        .two-col td { vertical-align: top; width: 50%; padding: 0; }
        .box { border: 1px solid #eef0f4; border-radius: 8px; padding: 10px 12px; margin: 6px 0; }
        .box h3 { margin: 0 0 6px 0; font-size: 13px; }
        .small { font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Daily ERP Digest <span class="badge">{{ $date }}</span></h1>
                <div class="meta">Generated at: {{ optional($d['generated_at'] ?? null)->format('d-m-Y H:i') ?? '' }}</div>
            </div>

            {{-- STORE --}}
            <div class="section">
                <h2>Store</h2>

                <div class="kpi">GRNs yesterday: <b>{{ $fmtInt($d['store']['inward']['grn_count'] ?? 0) }}</b></div>
                <div class="kpi">Items/Lines: <b>{{ $fmtInt($d['store']['inward']['line_count'] ?? 0) }}</b></div>
                <div class="kpi">Inward value (approx): <b>₹ {{ $fmtMoney($d['store']['inward']['value_total'] ?? 0) }}</b></div>
                <br>
                <div class="kpi">Issues yesterday: <b>{{ $fmtInt($d['store']['issue']['issue_count'] ?? 0) }}</b></div>
                <div class="kpi">Issue Lines: <b>{{ $fmtInt($d['store']['issue']['line_count'] ?? 0) }}</b></div>
                <div class="kpi">Issued value (approx): <b>₹ {{ $fmtMoney($d['store']['issue']['value_total'] ?? 0) }}</b></div>

                <div class="muted small" style="margin-top: 8px;">
                    Note: Values are estimated using PO item rates where available.
                </div>
            </div>

            {{-- PRODUCTION --}}
            <div class="section">
                <h2>Production</h2>
                <div class="kpi">DPRs submitted/approved: <b>{{ $fmtInt($d['production']['dpr_count'] ?? 0) }}</b></div>
                <div class="kpi">Total Qty reported: <b>{{ $fmtInt($d['production']['qty_total'] ?? 0) }}</b></div>
                <div class="kpi">Total Minutes: <b>{{ $fmtInt($d['production']['mins_total'] ?? 0) }}</b></div>

                @if(!empty($d['production']['projects']))
                    <div style="margin-top: 10px;">
                        <table class="grid">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th style="width: 90px;">DPRs</th>
                                    <th style="width: 130px;">Qty</th>
                                    <th style="width: 130px;">Minutes</th>
                                    <th style="width: 140px;">Completed Steps</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($d['production']['projects'] as $p)
                                    <tr>
                                        <td>{{ $p['project_code'] ?? '' }} - {{ $p['project_name'] ?? '' }}</td>
                                        <td>{{ $fmtInt($p['dpr_count'] ?? 0) }}</td>
                                        <td>{{ $fmtInt($p['qty_total'] ?? 0) }}</td>
                                        <td>{{ $fmtInt($p['mins_total'] ?? 0) }}</td>
                                        <td>{{ $fmtInt($p['completed_steps'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="muted" style="margin-top: 6px;">No DPR activity recorded yesterday.</div>
                @endif
            </div>

            {{-- CRM --}}
            <div class="section">
                <h2>CRM &amp; Quotations</h2>
                <table class="two-col">
                    <tr>
                        <td style="padding-right: 10px;">
                            <div class="box">
                                <h3>Leads</h3>
                                <div class="kpi">New leads: <b>{{ $fmtInt($d['crm']['leads_created'] ?? 0) }}</b></div>
                            </div>

                            <div class="box">
                                <h3>Activities</h3>
                                <div class="kpi">Logged: <b>{{ $fmtInt($d['crm']['activities_logged'] ?? 0) }}</b></div>
                                <div class="kpi">Completed: <b>{{ $fmtInt($d['crm']['activities_completed'] ?? 0) }}</b></div>
                            </div>
                        </td>
                        <td style="padding-left: 10px;">
                            <div class="box">
                                <h3>Quotations</h3>
                                <div class="kpi">Created: <b>{{ $fmtInt($d['crm']['quotations_created'] ?? 0) }}</b></div>
                                <div class="kpi">Created value: <b>₹ {{ $fmtMoney($d['crm']['quotations_created_value'] ?? 0) }}</b></div>
                                <br>
                                <div class="kpi">Sent: <b>{{ $fmtInt($d['crm']['quotations_sent'] ?? 0) }}</b></div>
                                <div class="kpi">Sent value: <b>₹ {{ $fmtMoney($d['crm']['quotations_sent_value'] ?? 0) }}</b></div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- PURCHASE --}}
            <div class="section">
                <h2>Purchase</h2>
                <div class="kpi">Open indents: <b>{{ $fmtInt($d['purchase']['open_indents'] ?? 0) }}</b></div>
                <div class="kpi">Approved (pending procurement): <b>{{ $fmtInt($d['purchase']['approved_pending_proc'] ?? 0) }}</b></div>

                @if(!empty($d['purchase']['by_procurement_status']))
                    <div style="margin-top: 10px;">
                        <table class="grid">
                            <thead>
                                <tr>
                                    <th>Procurement Status</th>
                                    <th style="width: 120px;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($d['purchase']['by_procurement_status'] as $s)
                                    <tr>
                                        <td>{{ ucwords(str_replace('_',' ', $s['status'] ?? 'open')) }}</td>
                                        <td>{{ $fmtInt($s['count'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($d['purchase']['overdue_required_by']))
                    <div style="margin-top: 12px;">
                        <div class="muted" style="margin-bottom: 6px;">Top overdue indents (required-by date passed)</div>
                        <table class="grid">
                            <thead>
                                <tr>
                                    <th>Indent</th>
                                    <th>Project</th>
                                    <th style="width: 120px;">Required By</th>
                                    <th style="width: 150px;">Procurement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($d['purchase']['overdue_required_by'] as $row)
                                    <tr>
                                        <td>{{ $row['code'] ?? '' }}</td>
                                        <td>{{ $row['project_code'] ?? '' }} {{ !empty($row['project_name']) ? '- '.$row['project_name'] : '' }}</td>
                                        <td>{{ !empty($row['required_by_date']) ? \Carbon\Carbon::parse($row['required_by_date'])->format('d-m-Y') : '-' }}</td>
                                        <td>{{ ucwords(str_replace('_',' ', $row['procurement_status'] ?? 'open')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- PAYMENTS --}}
            <div class="section">
                <h2>Payment Reminders</h2>
                <table class="two-col">
                    <tr>
                        <td style="padding-right: 10px;">
                            <div class="box">
                                <h3>Supplier Bills</h3>
                                <div class="kpi">Overdue: <b>{{ $fmtInt($d['payments']['supplier']['overdue_count'] ?? 0) }}</b></div>
                                <div class="kpi">Overdue value: <b>₹ {{ $fmtMoney($d['payments']['supplier']['overdue_value'] ?? 0) }}</b></div>
                                <br>
                                <div class="kpi">Due (next 7 days): <b>{{ $fmtInt($d['payments']['supplier']['due_soon_count'] ?? 0) }}</b></div>
                                <div class="kpi">Due value: <b>₹ {{ $fmtMoney($d['payments']['supplier']['due_soon_value'] ?? 0) }}</b></div>
                            </div>
                        </td>
                        <td style="padding-left: 10px;">
                            <div class="box">
                                <h3>Client Receivables</h3>
                                <div class="kpi">Overdue: <b>{{ $fmtInt($d['payments']['client']['overdue_count'] ?? 0) }}</b></div>
                                <div class="kpi">Overdue value: <b>₹ {{ $fmtMoney($d['payments']['client']['overdue_value'] ?? 0) }}</b></div>
                                <br>
                                <div class="kpi">Due (next 7 days): <b>{{ $fmtInt($d['payments']['client']['due_soon_count'] ?? 0) }}</b></div>
                                <div class="kpi">Due value: <b>₹ {{ $fmtMoney($d['payments']['client']['due_soon_value'] ?? 0) }}</b></div>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="muted small" style="margin-top: 8px;">
                    Payment reminders are calculated as of today.
                </div>
            </div>

            <div class="footer">
                This is an automated email from {{ config('app.name') }}.
            </div>
        </div>
    </div>
</body>
</html>
