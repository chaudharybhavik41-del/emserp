@php
    $qs = request()->query();
    $qsStr = http_build_query($qs);
    $qsSuffix = $qsStr ? ('?' . $qsStr) : '';
@endphp

<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('reports-hub.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Hub
    </a>

    <a href="{{ route('reports-hub.print', $report->key()) . $qsSuffix }}"
       target="_blank"
       class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-printer"></i> Print
    </a>

    <a href="{{ route('reports-hub.pdf', $report->key()) . $qsSuffix }}"
       class="btn btn-sm btn-outline-danger">
        <i class="bi bi-file-earmark-pdf"></i> PDF
    </a>

    <a href="{{ route('reports-hub.csv', $report->key()) . $qsSuffix }}"
       class="btn btn-sm btn-outline-success">
        <i class="bi bi-filetype-csv"></i> CSV
    </a>
</div>
