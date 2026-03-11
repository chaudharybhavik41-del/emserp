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

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.vouchers.index') }}" class="row g-2">
                <input type="hidden" name="wip_to_cogs" value="{{ $isWipToCogs ? '1' : '0' }}">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Voucher No...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>Reversed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        @foreach($voucherTypes as $val => $lbl)
                            <option value="{{ $val }}" {{ request('type') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                 <div class="col-md-2">
                    <label class="form-label small fw-bold">Project</label>
                    <select name="project_id" class="form-select form-select-sm project-select">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->code }} - {{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Cost Center</label>
                    <select name="cost_center_id" class="form-select form-select-sm cost-center-select">
                        <option value="">All Cost Centers</option>
                        @foreach($costCenters as $cc)
                            <option value="{{ $cc->id }}" {{ request('cost_center_id') == $cc->id ? 'selected' : '' }}>{{ $cc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary btn-sm px-3">Filter</button>
                    <a href="{{ route('accounting.vouchers.index', ['wip_to_cogs' => $isWipToCogs ? '1' : '0']) }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">

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
                        <th class="text-center">Status</th>
                        <th class="text-end" style="width: 120px;">Manage</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if(count($vouchers))
                        @foreach($vouchers as $voucher)
                            <tr>
                                <td>{{ $voucher->voucher_date?->format('d-m-Y') }}</td>
                                <td>
                                    <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="text-decoration-none fw-semibold">
                                        {{ $voucher->voucher_no }}
                                    </a>
                                    @if($voucher->reversal_of_voucher_id)
                                        <span class="badge bg-secondary ms-1">Reversal</span>
                                    @endif
                                    @if($voucher->reversal_voucher_id)
                                        <span class="badge bg-warning text-dark ms-1">Reversed</span>
                                    @endif

                                    @include('accounting.vouchers._voucher_doc_badge', ['voucher' => $voucher, 'docLinks' => $docLinks ?? []])
                                </td>
                                <td>{{ strtoupper($voucher->voucher_type) }}</td>
                                <td>
                                    @if($voucher->project)
                                        <div class="fw-bold small text-primary">{{ $voucher->project->code }}</div>
                                        <div class="small text-muted">{{ $voucher->project->name }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $voucher->costCenter?->name ?: '-' }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ number_format($voucher->amount_base, 2) }}</td>
                                <td class="text-center">
                                    @php
                                        $status = (string) ($voucher->status ?? '');
                                        $badge = match($status) {
                                            'posted' => 'bg-success',
                                            'draft' => 'bg-secondary',
                                            default => 'bg-light text-dark',
                                        };
                                        if ($voucher->reversal_voucher_id) { $badge = 'bg-warning text-dark'; $status = 'reversed'; }
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ ucfirst($status ?: 'n/a') }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('accounting.vouchers.show', $voucher) }}" class="btn btn-outline-primary btn-xs" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($voucher->isDraft())
                                            @can('accounting.vouchers.update')
                                                <a href="{{ route('accounting.vouchers.edit', $voucher) }}" class="btn btn-outline-secondary btn-xs" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan
                                            @can('accounting.vouchers.delete')
                                                <form action="{{ route('accounting.vouchers.destroy', $voucher) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-xs" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">No vouchers found.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
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
    $(document).ready(function() {
        $('.project-select').select2({
            width: '100%',
            placeholder: 'All Projects',
            allowClear: true
        });
        $('.cost-center-select').select2({
            width: '100%',
            placeholder: 'All Cost Centers',
            allowClear: true
        });
    });
</script>
@endpush
