@extends('reports_hub.layouts.a4')

@php
    $title = $report->name();
    $colCount = count($columns);
    $cls = 'rpt-cols-' . $colCount;

    // Build a quick filter summary (label: value)
    $filterSummary = [];
    foreach ($filterDefs as $f) {
        $name = $f['name'];
        $label = $f['label'] ?? $name;

        $val = $filters[$name] ?? request()->query($name, $f['default'] ?? null);
        if ($val === null || $val === '') continue;

        // if select, map to label
        if (($f['type'] ?? '') === 'select') {
            $mapped = null;
            foreach (($f['options'] ?? []) as $opt) {
                if ((string)($opt['value'] ?? '') === (string)$val) {
                    $mapped = (string)($opt['label'] ?? $val);
                    break;
                }
            }
            $val = $mapped ?? $val;
        }

        $filterSummary[] = [$label, (string)$val];
    }
@endphp

@section('content')
    <div class="rpt-header">
        <div style="display:flex; justify-content:space-between; gap: 12px;">
            <div>
                <p class="rpt-title">{{ $report->name() }}</p>
                <div class="rpt-meta">
                    {{ config('app.name', 'ERP') }}
                    &nbsp;•&nbsp;
                    Generated: {{ now()->format('d M Y, h:i A') }}
                </div>

                <div class="rpt-badges">
                    <span class="badge">{{ $report->module() }}</span>
                    <span class="badge">Rows: {{ is_countable($rows) ? count($rows) : 0 }}</span>
                </div>
            </div>

            <div class="no-print" style="text-align:right;">
                <a href="{{ url()->previous() }}" style="text-decoration:none; border:1px solid #e5e7eb; padding:6px 10px; border-radius:6px; color:#111827;">
                    Back
                </a>
                @if(($exportType ?? '') === 'print')
                    <a href="#" onclick="window.print(); return false;" style="text-decoration:none; border:1px solid #e5e7eb; padding:6px 10px; border-radius:6px; margin-left:6px; color:#111827;">
                        Print
                    </a>
                @endif
            </div>
        </div>

        @if(!empty($filterSummary))
            <div class="rpt-meta" style="margin-top:6px;">
                <strong>Filters:</strong>
                @foreach($filterSummary as $pair)
                    <span class="badge">{{ $pair[0] }}: {{ $pair[1] }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if(!empty($totals))
        <div class="rpt-summary">
            @foreach($totals as $t)
                <div class="box">
                    <div class="label">{{ $t['label'] ?? '' }}</div>
                    <div class="value">{{ $t['value'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="{{ $cls }}">
        <table class="rpt-table">
            <thead>
            <tr>
                @foreach($columns as $c)
                    @php
                        $width = $c['width'] ?? null;
                        $style = $width ? ('width:' . $width . ';') : '';
                        $align = $c['align'] ?? null;
                        $thClass = trim(($align === 'right' ? 'text-end' : ($align === 'center' ? 'text-center' : '')));
                    @endphp
                    <th class="{{ $thClass }}" style="{{ $style }}">{{ $c['label'] ?? '' }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $c)
                        @php
                            $align = $c['align'] ?? null;
                            $tdClass = trim(($align === 'right' ? 'text-end' : ($align === 'center' ? 'text-center' : '')));
                            $val = $report->resolveValue($c, $row, false);
                        @endphp
                        <td class="{{ $tdClass }}">
                            @if(is_string($val) && $val !== '')
                                {!! nl2br(e($val)) !!}
                            @else
                                {{ $val }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="text-center text-muted" style="padding: 18px;">
                        No data found for selected filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
