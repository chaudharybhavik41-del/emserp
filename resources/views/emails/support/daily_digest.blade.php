@php
    /**
     * Advanced email view for Daily Digest.
     *
     * Expects:
     * - $digest: array (from DailyDigestService)
     */
    $d = $digest;
    $date = (string) ($d['date'] ?? '');
    $insights = $d['insights'] ?? [];
    $score = $insights['scorecard'] ?? [];
    $alerts = $insights['alerts'] ?? [];
    $actions = $insights['actions'] ?? [];

    $fmtMoney = function ($v) {
        return number_format((float) ($v ?? 0), 2);
    };
    $fmtInt = function ($v) {
        return number_format((int) ($v ?? 0));
    };
    $fmtDecimal = function ($v, $decimals = 2) {
        return number_format((float) ($v ?? 0), $decimals);
    };
    $fmtPercent = function ($v) use ($fmtDecimal) {
        return $fmtDecimal($v, 1) . '%';
    };
    $prettyStatus = function ($v) {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            $s = 'open';
        }

        return ucwords(str_replace('_', ' ', $s));
    };
    $alertStyle = function ($level) {
        return match ($level) {
            'danger' => ['bg' => '#fff1f2', 'border' => '#fecdd3', 'text' => '#be123c'],
            'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#b45309'],
            'info' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1d4ed8'],
            'success' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#047857'],
            default => ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#334155'],
        };
    };
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily ERP Digest</title>
    <style>
        body { margin: 0; padding: 0; background: #eef2f6; font-family: Arial, Helvetica, sans-serif; color: #14213d; }
        table { border-collapse: collapse; }
        .shell { width: 100%; background: #eef2f6; padding: 24px 0; }
        .wrap { width: 920px; max-width: 920px; margin: 0 auto; }
        .card { background: #ffffff; border: 1px solid #dbe4ee; border-radius: 14px; overflow: hidden; }
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1d3557 100%); color: #ffffff; padding: 24px 28px; }
        .hero h1 { margin: 0; font-size: 24px; line-height: 1.2; }
        .hero p { margin: 8px 0 0; font-size: 13px; color: #dbeafe; }
        .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,0.14); color: #ffffff; font-size: 11px; letter-spacing: 0.04em; text-transform: uppercase; }
        .section { padding: 22px 28px; border-top: 1px solid #edf2f7; }
        .section h2 { margin: 0 0 12px; font-size: 16px; color: #0f172a; }
        .muted { color: #64748b; }
        .summary-grid td { width: 25%; padding: 10px; }
        .summary-card { border: 1px solid #dbe4ee; border-radius: 12px; padding: 14px; background: #f8fafc; }
        .summary-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-value { margin-top: 6px; font-size: 22px; font-weight: 700; color: #0f172a; }
        .summary-note { margin-top: 4px; font-size: 12px; color: #64748b; }
        .focus-box { border: 1px solid #bfdbfe; border-radius: 12px; background: #eff6ff; padding: 16px; }
        .focus-title { font-size: 12px; color: #1d4ed8; text-transform: uppercase; letter-spacing: 0.05em; }
        .focus-message { margin-top: 6px; font-size: 18px; font-weight: 700; color: #1e3a8a; }
        .alert-box { border: 1px solid #dbe4ee; border-radius: 12px; padding: 14px; }
        .alert-title { font-size: 13px; font-weight: 700; }
        .alert-message { margin-top: 4px; font-size: 13px; line-height: 1.5; }
        .list { margin: 0; padding-left: 18px; color: #334155; }
        .list li { margin: 0 0 8px; }
        .metric-table { width: 100%; }
        .metric-table td { width: 50%; padding: 10px 12px; border: 1px solid #e2e8f0; font-size: 13px; }
        .metric-table .label { color: #64748b; }
        .metric-table .value { font-weight: 700; color: #0f172a; text-align: right; }
        .detail-grid td { vertical-align: top; width: 50%; padding: 0 8px 0 0; }
        .detail-grid td:last-child { padding-right: 0; padding-left: 8px; }
        .box { border: 1px solid #dbe4ee; border-radius: 12px; padding: 14px; background: #ffffff; }
        .box h3 { margin: 0 0 10px; font-size: 14px; color: #0f172a; }
        .kpi { margin-bottom: 8px; font-size: 13px; color: #334155; }
        .kpi strong { color: #0f172a; }
        .grid { width: 100%; }
        .grid th, .grid td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; font-size: 12px; }
        .grid th { background: #f8fafc; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; font-size: 11px; }
        .right { text-align: right; }
        .footer { padding: 16px 28px 24px; border-top: 1px solid #edf2f7; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <table role="presentation" class="shell" width="100%">
        <tr>
            <td align="center">
                <table role="presentation" class="wrap" width="920">
                    <tr>
                        <td>
                            <table role="presentation" class="card" width="100%">
                                <tr>
                                    <td class="hero">
                                        <span class="pill">Daily ERP Digest</span>
                                        <h1>{{ config('app.name') }} Operational Digest</h1>
                                        <p>
                                            Digest Date: <strong>{{ $date }}</strong>
                                            &nbsp;|&nbsp;
                                            Generated At: <strong>{{ optional($d['generated_at'] ?? null)->format('d-m-Y H:i') ?? '' }}</strong>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="section">
                                        <h2>Executive Snapshot</h2>
                                        <table role="presentation" class="summary-grid" width="100%">
                                            <tr>
                                                <td>
                                                    <div class="summary-card">
                                                        <div class="summary-label">Net Store Movement</div>
                                                        <div class="summary-value">₹ {{ $fmtMoney($score['store_net_value'] ?? 0) }}</div>
                                                        <div class="summary-note">Inward value minus issue value</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="summary-card">
                                                        <div class="summary-label">Avg Qty / DPR</div>
                                                        <div class="summary-value">{{ $fmtDecimal($score['avg_qty_per_dpr'] ?? 0) }}</div>
                                                        <div class="summary-note">Production output per reported DPR</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="summary-card">
                                                        <div class="summary-label">Quote Send Rate</div>
                                                        <div class="summary-value">{{ $fmtPercent($score['quote_send_rate'] ?? 0) }}</div>
                                                        <div class="summary-note">Sent quotations vs created quotations</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="summary-card">
                                                        <div class="summary-label">Working Capital Pressure</div>
                                                        <div class="summary-value">₹ {{ $fmtMoney($score['working_capital_pressure'] ?? 0) }}</div>
                                                        <div class="summary-note">Supplier overdue minus client overdue</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="section">
                                        <div class="focus-box">
                                            <div class="focus-title">{{ $insights['headline']['title'] ?? 'Executive Summary' }}</div>
                                            <div class="focus-message">{{ $insights['headline']['message'] ?? 'No headline generated.' }}</div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="section">
                                        <h2>What Needs Attention</h2>
                                        <table role="presentation" width="100%">
                                            @foreach($alerts as $alert)
                                                @php($style = $alertStyle($alert['level'] ?? 'info'))
                                                <tr>
                                                    <td style="padding-bottom: 10px;">
                                                        <div class="alert-box" style="background: {{ $style['bg'] }}; border-color: {{ $style['border'] }};">
                                                            <div class="alert-title" style="color: {{ $style['text'] }};">{{ $alert['title'] ?? 'Update' }}</div>
                                                            <div class="alert-message" style="color: {{ $style['text'] }};">{{ $alert['message'] ?? '' }}</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>

                                @if(!empty($actions))
                                    <tr>
                                        <td class="section">
                                            <h2>Recommended Actions</h2>
                                            <ol class="list">
                                                @foreach($actions as $action)
                                                    <li>{{ $action }}</li>
                                                @endforeach
                                            </ol>
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td class="section">
                                        <h2>Performance Indicators</h2>
                                        <table role="presentation" class="metric-table">
                                            <tr>
                                                <td class="label">Average minutes per DPR</td>
                                                <td class="value">{{ $fmtDecimal($score['avg_minutes_per_dpr'] ?? 0, 1) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Minutes per reported quantity</td>
                                                <td class="value">{{ $fmtDecimal($score['minutes_per_qty'] ?? 0) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Average quotation value</td>
                                                <td class="value">₹ {{ $fmtMoney($score['avg_quote_value'] ?? 0) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Approved indents pending procurement</td>
                                                <td class="value">{{ $fmtInt($score['approved_pending_proc'] ?? 0) }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="section">
                                        <h2>Operational Detail</h2>
                                        <table role="presentation" class="detail-grid" width="100%">
                                            <tr>
                                                <td>
                                                    <div class="box">
                                                        <h3>Store</h3>
                                                        <div class="kpi">GRNs on {{ $date }}: <strong>{{ $fmtInt($d['store']['inward']['grn_count'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Inward lines: <strong>{{ $fmtInt($d['store']['inward']['line_count'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Inward value: <strong>₹ {{ $fmtMoney($d['store']['inward']['value_total'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Issues on {{ $date }}: <strong>{{ $fmtInt($d['store']['issue']['issue_count'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Issue value: <strong>₹ {{ $fmtMoney($d['store']['issue']['value_total'] ?? 0) }}</strong></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="box">
                                                        <h3>Production</h3>
                                                        <div class="kpi">Submitted/approved DPRs: <strong>{{ $fmtInt($d['production']['dpr_count'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Total quantity reported: <strong>{{ $fmtDecimal($d['production']['qty_total'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Total minutes: <strong>{{ $fmtInt($d['production']['mins_total'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Completed steps: <strong>{{ $fmtInt(collect($d['production']['projects'] ?? [])->sum('completed_steps')) }}</strong></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="box">
                                                        <h3>Commercial</h3>
                                                        <div class="kpi">Leads created: <strong>{{ $fmtInt($d['crm']['leads_created'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Activities logged: <strong>{{ $fmtInt($d['crm']['activities_logged'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Activities completed: <strong>{{ $fmtInt($d['crm']['activities_completed'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Quotations created: <strong>{{ $fmtInt($d['crm']['quotations_created'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Quotation value created: <strong>₹ {{ $fmtMoney($d['crm']['quotations_created_value'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Quotations sent: <strong>{{ $fmtInt($d['crm']['quotations_sent'] ?? 0) }}</strong></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="box">
                                                        <h3>Cash & Procurement</h3>
                                                        <div class="kpi">Open indents: <strong>{{ $fmtInt($d['purchase']['open_indents'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Approved pending procurement: <strong>{{ $fmtInt($d['purchase']['approved_pending_proc'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Supplier overdue value: <strong>₹ {{ $fmtMoney($d['payments']['supplier']['overdue_value'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Supplier due next 7 days: <strong>₹ {{ $fmtMoney($d['payments']['supplier']['due_soon_value'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Client overdue value: <strong>₹ {{ $fmtMoney($d['payments']['client']['overdue_value'] ?? 0) }}</strong></div>
                                                        <div class="kpi">Client due next 7 days: <strong>₹ {{ $fmtMoney($d['payments']['client']['due_soon_value'] ?? 0) }}</strong></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                @if(!empty($d['production']['projects']))
                                    <tr>
                                        <td class="section">
                                            <h2>Top Production Projects</h2>
                                            <table role="presentation" class="grid" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th>Project</th>
                                                        <th class="right">DPRs</th>
                                                        <th class="right">Qty</th>
                                                        <th class="right">Minutes</th>
                                                        <th class="right">Completed Steps</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($d['production']['projects'] as $project)
                                                        <tr>
                                                            <td>{{ $project['project_code'] ?? '' }}{{ !empty($project['project_name']) ? ' - ' . $project['project_name'] : '' }}</td>
                                                            <td class="right">{{ $fmtInt($project['dpr_count'] ?? 0) }}</td>
                                                            <td class="right">{{ $fmtDecimal($project['qty_total'] ?? 0) }}</td>
                                                            <td class="right">{{ $fmtInt($project['mins_total'] ?? 0) }}</td>
                                                            <td class="right">{{ $fmtInt($project['completed_steps'] ?? 0) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                @if(!empty($d['purchase']['overdue_required_by']))
                                    <tr>
                                        <td class="section">
                                            <h2>Oldest Overdue Purchase Indents</h2>
                                            <table role="presentation" class="grid" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th>Indent</th>
                                                        <th>Project</th>
                                                        <th class="right">Required By</th>
                                                        <th class="right">Procurement</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($d['purchase']['overdue_required_by'] as $row)
                                                        <tr>
                                                            <td>{{ $row['code'] ?? '' }}</td>
                                                            <td>{{ $row['project_code'] ?? '' }}{{ !empty($row['project_name']) ? ' - ' . $row['project_name'] : '' }}</td>
                                                            <td class="right">{{ !empty($row['required_by_date']) ? \Carbon\Carbon::parse($row['required_by_date'])->format('d-m-Y') : '-' }}</td>
                                                            <td class="right">{{ $prettyStatus($row['procurement_status'] ?? 'open') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td class="footer">
                                        This digest is generated automatically by {{ config('app.name') }}.
                                        Payment reminders are evaluated relative to the day the mail is sent.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
