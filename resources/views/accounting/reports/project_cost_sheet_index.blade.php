@extends('layouts.erp')

@section('title', 'Project Cost Sheet')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">Project Cost Sheet</h1>
            <small class="text-muted">Project-wise cost analysis as of {{ $asOfDate->format('d-M-Y') }}</small>
        </div>
        <div>
            <form method="GET" class="d-flex gap-2 align-items-end">
                <div>
                    <label class="form-label form-label-sm mb-1">As on date</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate->format('Y-m-d') }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
            </form>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Material Cost</h6>
                    <h4 class="mb-0">₹ {{ number_format($grandTotals['material'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Consumables</h6>
                    <h4 class="mb-0">₹ {{ number_format($grandTotals['consumables'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="card-title">Subcontractor Cost</h6>
                    <h4 class="mb-0">₹ {{ number_format($grandTotals['subcontractor'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Project Cost</h6>
                    <h4 class="mb-0">₹ {{ number_format($grandTotals['total'], 2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <label class="form-label form-label-sm">Quick search projects</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="pcsProjectSearch" class="form-control" placeholder="Project name/code...">
                <button type="button" id="pcsProjectSearchClear" class="btn btn-outline-secondary">Clear</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Project-wise Cost Summary</h5>
            <span class="small text-muted">{{ count($projectCosts) }} project(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Project</th>
                            <th class="text-end">Material</th>
                            <th class="text-end">Consumables</th>
                            <th class="text-end">Subcontractor</th>
                            <th class="text-end">Other Direct</th>
                            <th class="text-end">Total Cost</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="pcsNoMatchRow" class="d-none">
                            <td colspan="7" class="text-center py-4 text-muted">No projects match the search text.</td>
                        </tr>
                        @forelse($projectCosts as $data)
                            @php
                                $searchText = strtolower(trim(($data['project']->name ?? '') . ' ' . ($data['project']->code ?? '')));
                            @endphp
                            <tr class="pcs-project-row" data-row-text="{{ $searchText }}">
                                <td>
                                    <a href="{{ route('accounting.reports.project-cost-sheet.show', ['project' => $data['project'], 'as_of_date' => $asOfDate->format('Y-m-d')]) }}">
                                        <strong>{{ $data['project']->name }}</strong>
                                    </a>
                                    <br>
                                    <small class="text-muted">{{ $data['project']->code }}</small>
                                </td>
                                <td class="text-end">₹ {{ number_format($data['costs']['material'] ?? 0, 2) }}</td>
                                <td class="text-end">₹ {{ number_format($data['costs']['consumables'] ?? 0, 2) }}</td>
                                <td class="text-end">₹ {{ number_format($data['costs']['subcontractor'] ?? 0, 2) }}</td>
                                <td class="text-end">₹ {{ number_format($data['costs']['other_direct'] ?? 0, 2) }}</td>
                                <td class="text-end"><strong>₹ {{ number_format($data['total_cost'], 2) }}</strong></td>
                                <td class="text-center">
                                    <a href="{{ route('accounting.reports.project-cost-sheet.show', ['project' => $data['project'], 'as_of_date' => $asOfDate->format('Y-m-d')]) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="{{ route('accounting.reports.project-cost-sheet.export', ['project' => $data['project'], 'date_to' => $asOfDate->format('Y-m-d')]) }}"
                                       class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-download"></i> Export
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No project costs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($projectCosts) > 0)
                    <tfoot class="table-secondary">
                        <tr class="fw-bold">
                            <td>GRAND TOTAL</td>
                            <td class="text-end">₹ {{ number_format($grandTotals['material'], 2) }}</td>
                            <td class="text-end">₹ {{ number_format($grandTotals['consumables'], 2) }}</td>
                            <td class="text-end">₹ {{ number_format($grandTotals['subcontractor'], 2) }}</td>
                            <td class="text-end">₹ {{ number_format($grandTotals['other_direct'], 2) }}</td>
                            <td class="text-end">₹ {{ number_format($grandTotals['total'], 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('pcsProjectSearch');
    const clearBtn = document.getElementById('pcsProjectSearchClear');
    const rows = Array.from(document.querySelectorAll('.pcs-project-row'));
    const noMatch = document.getElementById('pcsNoMatchRow');
    if (!input || !rows.length) return;

    const applyFilter = function () {
        const needle = (input.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const hay = (row.dataset.rowText || row.textContent || '').toLowerCase();
            const show = needle === '' || hay.includes(needle);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        if (noMatch) noMatch.classList.toggle('d-none', needle === '' || visible > 0);
    };

    input.addEventListener('input', applyFilter);
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter();
        });
    }

    applyFilter();
});
</script>
@endpush
