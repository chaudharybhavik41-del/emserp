@php
    $colCount = count($columns);
    $cls = 'rpt-cols-' . $colCount;
    $fixed = $fixedLayout ?? false;
@endphp

<div class="{{ $cls }}">
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle rpt-table {{ $fixed ? 'rpt-fixed' : '' }}">
            <thead>
            <tr>
                @foreach($columns as $c)
                    @php
                        $width = $c['width'] ?? null;
                        $style = $width ? ('width:' . $width . ';') : '';
                        $align = $c['align'] ?? null;
                        $thClass = trim('rpt-th ' . ($c['class'] ?? '') . ' ' . ($align === 'right' ? 'text-end' : ($align === 'center' ? 'text-center' : '')));
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
                            $tdClass = trim('rpt-td ' . ($c['class'] ?? '') . ' ' . ($align === 'right' ? 'text-end' : ($align === 'center' ? 'text-center' : '')));
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
                    <td colspan="{{ $colCount }}" class="text-center text-muted py-4">
                        <i class="bi bi-inbox me-1"></i> No data found for selected filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
