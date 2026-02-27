@extends('layouts.erp')

@section('title', $report->name())

@section('page_header')
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h5 mb-0">{{ $report->name() }}</h1>
            <span class="badge text-bg-light">{{ $report->module() }}</span>
        </div>
        @if($report->description())
            <div class="text-muted small">{{ $report->description() }}</div>
        @endif
    </div>

    @include('reports_hub.partials.actions', ['report' => $report])
@endsection

@section('content')
<div class="container-fluid">

    @include('reports_hub.partials.filters', [
        'report' => $report,
        'filters' => $filters,
        'filterDefs' => $filterDefs,
    ])

    @if(!empty($totals))
        <div class="row g-2 mb-3">
            @foreach($totals as $t)
                <div class="col-12 col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-2">
                            <div class="text-muted small">{{ $t['label'] ?? '' }}</div>
                            <div class="fw-semibold">{{ $t['value'] ?? '' }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('reports_hub.partials.table', [
                'report' => $report,
                'columns' => $columns,
                'rows' => $rows,
                'fixedLayout' => false,
            ])
        </div>

        @if(method_exists($rows, 'links'))
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="text-muted small">
                        Showing {{ $rows->firstItem() ?? 0 }} - {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() ?? 0 }}
                    </div>
                    <div>
                        {{ $rows->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

</div>
@endsection

@push('styles')
<style>
/* Screen table improvements */
.rpt-table th, .rpt-table td { vertical-align: top; }
.rpt-table td { white-space: normal; word-break: break-word; }
.rpt-table.rpt-fixed { table-layout: fixed; }
</style>
@endpush
