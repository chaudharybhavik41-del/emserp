<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vendor RFQ Portal')</title>

    <style>
        :root{
            --bg:#f6f8fb;
            --card:#ffffff;
            --text:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --primary:#2563eb;
            --primary-600:#1d4ed8;
            --success:#16a34a;
            --danger:#dc2626;
            --warning:#f59e0b;
            --shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            color:var(--text);
            background:
                radial-gradient(900px 380px at 20% -10%, rgba(37,99,235,0.10), transparent 60%),
                radial-gradient(900px 380px at 80% -10%, rgba(99,102,241,0.10), transparent 60%),
                var(--bg);
        }

        .wrap{max-width:1600px;margin:22px auto;padding:0 20px;}
        .topbar{
            background:linear-gradient(135deg, rgba(37,99,235,0.95), rgba(99,102,241,0.95));
            color:#fff;
            border-radius:16px;
            padding:16px 18px;
            box-shadow: var(--shadow);
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .brand{display:flex;flex-direction:column;line-height:1.15}
        .brand .name{font-weight:800;font-size:16px;letter-spacing:.2px}
        .brand .sub{opacity:.9;font-size:13px}
        .topbar .right{font-size:12px;opacity:.95;text-align:right}
        .topbar .right .pill{
            display:inline-block;
            padding:4px 10px;
            border-radius:999px;
            border:1px solid rgba(255,255,255,.22);
            background:rgba(255,255,255,.12);
            margin-top:6px;
        }

        .content{margin-top:16px;}

        .card{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:16px;
            box-shadow: var(--shadow);
            margin-bottom:16px;
            overflow:hidden;
        }
        .card-h{
            padding:12px 16px;
            border-bottom:1px solid var(--border);
            font-weight:800;
            background:linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .card-b{padding:16px;}

        .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px;}
        .col-3{grid-column:span 3;}
        .col-4{grid-column:span 4;}
        .col-6{grid-column:span 6;}
        .col-8{grid-column:span 8;}
        .col-12{grid-column:span 12;}

        label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;}
        input, textarea{
            width:100%;
            border:1px solid var(--border);
            border-radius:12px;
            padding:10px 12px;
            font-size:14px;
            background:#fff;
            outline:none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        input:focus, textarea:focus{
            border-color: rgba(37,99,235,.6);
            box-shadow: 0 0 0 4px rgba(37,99,235,.12);
        }
        input[disabled], textarea[disabled]{
            background:#f1f5f9;
            color:#0f172a;
        }
        textarea{min-height:72px;resize:vertical;}

        .muted{color:var(--muted);}
        .small{font-size:12px;}
        .text-end{text-align:right;}

        .badge{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 10px;
            border-radius:999px;
            border:1px solid var(--border);
            background:#f8fafc;
            font-size:12px;
            color:var(--text);
            font-weight:700;
        }
        .badge.primary{border-color:rgba(37,99,235,.25); background:rgba(37,99,235,.08); color:var(--primary-600);}
        .badge.warn{border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.12); color:#92400e;}
        .badge.success{border-color:rgba(22,163,74,.28); background:rgba(22,163,74,.10); color:#166534;}
        .badge.danger{border-color:rgba(220,38,38,.28); background:rgba(220,38,38,.10); color:#7f1d1d;}

        .alert{
            border-radius:14px;
            padding:12px 14px;
            border:1px solid var(--border);
            background:#fff;
            margin-bottom:12px;
            position:relative;
        }
        .alert .close{
            position:absolute;
            right:10px; top:8px;
            border:none;
            background:transparent;
            font-size:18px;
            cursor:pointer;
            color:inherit;
            opacity:.6;
        }
        .alert .close:hover{opacity:1}
        .alert.success{border-color:rgba(22,163,74,.25);background:rgba(22,163,74,.08);color:#166534;}
        .alert.error{border-color:rgba(220,38,38,.25);background:rgba(220,38,38,.08);color:#7f1d1d;}
        .alert.info{border-color:rgba(37,99,235,.25);background:rgba(37,99,235,.08);color:#1e3a8a;}
        .alert.warn{border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.12);color:#92400e;}

        table{
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            font-size:13px;
        }
        th, td{
            border-bottom:1px solid var(--border);
            padding:10px 10px;
            vertical-align:top;
        }
        thead th{
            position:sticky;
            top:0;
            z-index:2;
            background:#f8fafc;
            border-top:1px solid var(--border);
            border-bottom:1px solid var(--border);
            font-weight:800;
            color:#0f172a;
        }
        tbody tr:nth-child(even){background:#fcfcfd;}
        tbody tr:hover{background:#f8fafc;}
        tbody td input{
            padding:8px 10px;
            border-radius:10px;
        }

        .btnbar{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            justify-content:flex-end;
            padding:12px 16px;
            border-top:1px solid var(--border);
            background:linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            border:1px solid var(--border);
            border-radius:12px;
            padding:10px 14px;
            font-weight:800;
            cursor:pointer;
            text-decoration:none;
            background:#fff;
            color:var(--text);
            transition: transform .05s ease, border-color .15s ease, background .15s ease;
        }
        .btn:hover{border-color:#cbd5e1; background:#f8fafc}
        .btn:active{transform: translateY(1px);}
        .btn.primary{background:var(--primary);border-color:var(--primary);color:#fff;}
        .btn.primary:hover{background:var(--primary-600);border-color:var(--primary-600);}
        .btn.ghost{background:transparent;}

        .footer{
            padding:10px 4px 2px;
            color:var(--muted);
            font-size:12px;
            text-align:center;
        }

        @media (max-width: 900px){
            .col-3,.col-4,.col-6,.col-8{grid-column:span 12;}
            .topbar{border-radius:14px;}
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div class="brand">
            <div class="name">{{ config('app.name') }}</div>
            <div class="sub">Vendor Quotation Portal</div>
        </div>
        <div class="right">
            <div class="pill">Secure link • Do not forward</div>
        </div>
    </div>

    <div class="content">
        @if(session('success'))
            <div class="alert success">
                <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert error">
                <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert error">
                <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                <div style="font-weight:800;margin-bottom:6px;">Please fix the following:</div>
                <ul style="margin:0;padding-left:18px;">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </div>
</div>
</body>
</html>
