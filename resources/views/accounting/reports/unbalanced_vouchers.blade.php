@extends('layouts.erp')

@section('title', 'Unbalanced Vouchers')

@section('content')
@php
    $from = optional($fromDate)->toDateString();
    $to = optional($toDate)->toDateString();
    $voucherCount = count($rows);
    $absDiffTotal = collect($rows)->sum(fn($r) => abs((float) $r->diff));
    $maxDiff = collect($rows)->map(fn($r) => abs((float) $r->diff))->max() ?? 0;
@endphp
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Unbalanced Vouchers (Validation)</h1>
            <div class="small text-muted">Company #{{ $companyId }} · {{ $from }} to {{ $to }}</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Unbalanced vouchers</div>
                    <div class="h5 mb-0">{{ $voucherCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Total absolute diff</div>
                    <div class="h5 mb-0">{{ number_format($absDiffTotal, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Max single voucher diff</div>
                    <div class="h5 mb-0 text-danger">{{ number_format($maxDiff, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning">
        <div class="small mb-0">
            This report lists <strong>posted vouchers</strong> where total <strong>Debit</strong> is not equal to total <strong>Credit</strong>.
            If this list is empty, your double-entry data is internally consistent.
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date"
                           name="from_date"
                           value="{{ request('from_date', $from) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date"
                           name="to_date"
                           value="{{ request('to_date', $to) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Voucher type (optional)</label>
                    <select name="voucher_type" class="form-select form-select-sm">
                        <option value="">-- All types --</option>
                        @foreach($voucherTypes as $vt)
                            <option value="{{ $vt }}" @selected($type === $vt)>
                                {{ strtoupper($vt) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label form-label-sm">Project (optional)</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">-- All projects --</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected((int)request('project_id') === $p->id)>
                                {{ $p->code }} - {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-filter"></i> View
                        </button>
                        <a href="{{ route('accounting.reports.unbalanced-vouchers') }}" class="btn btn-outline-secondary btn-sm">
                            Reset
                        </a>
                        <a href="{{ route('accounting.reports.unbalanced-vouchers', array_merge(request()->all(), ['export' => 'csv'])) }}"
                           class="btn btn-outline-success btn-sm">
                            <i class="bi bi-download"></i> Export CSV
                        </a>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label form-label-sm">Quick search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="ubSearch" class="form-control" placeholder="Voucher no/type/project/reference/narration...">
                        <button type="button" id="ubSearchClear" class="btn btn-outline-secondary">Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="fw-semibold small">
                Unbalanced vouchers: {{ $from }} to {{ $to }}
                @if($type)
                    ({{ strtoupper($type) }} only)
                @endif
            </div>
            <div class="small text-muted">Posted vouchers only</div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%;">Date</th>
                            <th style="width: 12%;">Voucher No</th>
                            <th style="width: 10%;">Type</th>
                            <th style="width: 18%;">Project</th>
                            <th style="width: 18%;">Reference</th>
                            <th style="width: 20%;">Narration</th>
                            <th style="width: 10%;" class="text-end">Debit</th>
                            <th style="width: 10%;" class="text-end">Credit</th>
                            <th style="width: 10%;" class="text-end">Diff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="ubNoMatch" class="d-none">
                            <td colspan="9" class="text-center small text-muted py-3">No vouchers match the search text.</td>
                        </tr>
                        @if($voucherCount)
                            @foreach($rows as $r)
                                @php
                                    $diff = (float) $r->diff;
                                    $proj = $r->project_id ? ($projectMap[$r->project_id]->code ?? ('#' . $r->project_id)) : '';
                                    $searchText = strtolower(trim(
                                        ($r->voucher_date ?? '') . ' ' .
                                        ($r->voucher_no ?? '') . ' ' .
                                        ($r->voucher_type ?? '') . ' ' .
                                        ($proj ?? '') . ' ' .
                                        ($r->reference ?? '') . ' ' .
                                        ($r->narration ?? '')
                                    ));
                                @endphp
                                <tr class="ub-row {{ abs($diff) >= 0.01 ? 'table-warning' : '' }}"
                                    data-row-text="{{ $searchText }}">
                                    <td class="small">{{ $r->voucher_date }}</td>
                                    <td class="small">
                                        <a href="{{ route('accounting.vouchers.show', $r->id) }}" class="text-decoration-none">
                                            {{ $r->voucher_no }}
                                        </a>
                                    </td>
                                    <td class="small text-uppercase">{{ $r->voucher_type }}</td>
                                    <td class="small">{{ $proj }}</td>
                                    <td class="small">{{ $r->reference }}</td>
                                    <td class="small">{{ $r->narration }}</td>
                                    <td class="small text-end">{{ number_format((float)$r->debit_total, 2) }}</td>
                                    <td class="small text-end">{{ number_format((float)$r->credit_total, 2) }}</td>
                                    <td class="small text-end fw-semibold {{ abs($diff) >= 0.01 ? 'text-danger' : '' }}">
                                        {{ number_format($diff, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="9" class="text-center small text-muted py-3">
                                    No unbalanced vouchers found for the selected criteria.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.ub-row:hover td { background: #fff8e8; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('ubSearch');
    const clearBtn = document.getElementById('ubSearchClear');
    const rows = Array.from(document.querySelectorAll('.ub-row'));
    const noMatch = document.getElementById('ubNoMatch');
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
