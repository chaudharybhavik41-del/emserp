@extends('layouts.erp')

@section('title', 'Vouchers')

@section('content')
<div class="container-fluid">
    @php
        $isWipToCogs = $isWipToCogs ?? request()->boolean('wip_to_cogs');
        $voucherRows = method_exists($vouchers, 'getCollection') ? $vouchers->getCollection() : collect($vouchers);
        $postedCount = $voucherRows->where('status', 'posted')->count();
        $draftCount = $voucherRows->where('status', 'draft')->count();
        $pageAmount = $voucherRows->sum(fn($v) => (float) ($v->amount_base ?? 0));
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            Vouchers
            @if($isWipToCogs)
                <span class="badge bg-info text-dark ms-2">WIP → COGS Drafts</span>
            @endif
        </h1>

        <div class="d-flex gap-2">
            @if($isWipToCogs)
                <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">
                    View All
                </a>
            @endif

            @can('accounting.vouchers.create')
                <a href="{{ route('accounting.vouchers.create') }}" class="btn btn-primary btn-sm">Add Voucher</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Rows on this page</div>
                    <div class="h6 mb-0">{{ count($vouchers) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Posted</div>
                    <div class="h6 mb-0 text-success">{{ $postedCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Draft</div>
                    <div class="h6 mb-0 text-secondary">{{ $draftCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted">Page total amount</div>
                    <div class="h6 mb-0">{{ number_format($pageAmount, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label form-label-sm">Quick search vouchers</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="voucherQuickSearch" class="form-control" placeholder="No/type/project/cost center/status...">
                    <button type="button" id="voucherQuickSearchClear" class="btn btn-outline-secondary">Clear</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>No.</th>
                        <th>Type</th>
                        <th>Project</th>
                        <th>Cost Center</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Posted At</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr id="voucherNoMatchRow" class="d-none">
                        <td colspan="8" class="text-center text-muted py-3">No vouchers match the search text.</td>
                    </tr>
                    @if(count($vouchers))
                        @foreach($vouchers as $voucher)
                            @php
                                $searchText = strtolower(trim(
                                    ($voucher->voucher_date?->format('Y-m-d') ?? '') . ' ' .
                                    ($voucher->voucher_no ?? '') . ' ' .
                                    ($voucher->voucher_type ?? '') . ' ' .
                                    ($voucher->project?->code ?? '') . ' ' .
                                    ($voucher->costCenter?->name ?? '') . ' ' .
                                    ($voucher->status ?? '')
                                ));
                            @endphp
                            <tr class="voucher-row" data-row-text="{{ $searchText }}">
                                <td>{{ $voucher->voucher_date?->format('d-m-Y') }}</td>
                                <td>
                                    <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="text-decoration-none">
                                        {{ $voucher->voucher_no }}
                                    </a>
                                    @if($voucher->reversal_of_voucher_id)
                                        <span class="badge bg-secondary ms-1">Reversal</span>
                                    @endif
                                    @if($voucher->reversal_voucher_id)
                                        <span class="badge bg-warning text-dark ms-1">Reversed</span>
                                    @endif

                                    {{-- ✅ CN/DN badge + link --}}
                                    @include('accounting.vouchers._voucher_doc_badge', ['voucher' => $voucher, 'docLinks' => $docLinks ?? []])
                                </td>
                                <td>{{ $voucher->voucher_type }}</td>
                                <td>{{ $voucher->project?->code }}</td>
                                <td>{{ $voucher->costCenter?->name }}</td>
                                <td class="text-end">{{ number_format($voucher->amount_base, 2) }}</td>
                                <td>
                                    @php
                                        $status = (string) ($voucher->status ?? '');
                                        $badge = match($status) {
                                            'posted' => 'bg-success',
                                            'draft' => 'bg-secondary',
                                            default => 'bg-light text-dark',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ ucfirst($status ?: 'n/a') }}</span>
                                </td>
                                <td class="small text-muted">{{ $voucher->posted_at?->format('d-m-Y H:i') }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">No vouchers yet.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

        @if($vouchers->hasPages())
            <div class="card-footer">
                {{ $vouchers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('voucherQuickSearch');
    const clearBtn = document.getElementById('voucherQuickSearchClear');
    const rows = Array.from(document.querySelectorAll('.voucher-row'));
    const noMatch = document.getElementById('voucherNoMatchRow');
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
