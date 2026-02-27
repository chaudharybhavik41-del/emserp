@extends('layouts.erp')

@section('content')
@php
    // Controller passes $adjustment (not $storeStockAdjustment)
    /** @var \App\Models\StoreStockAdjustment $adjustment */
    $acc = $adjustment->accounting_status ?? 'pending';
    $isOpening = ($adjustment->adjustment_type ?? null) === 'opening';
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        Stock Adjustment
        @if(!empty($adjustment->reference_number))
            - {{ $adjustment->reference_number }}
        @endif
    </h1>

    <div class="d-flex gap-2">
        @can('store.stock.adjustment.create')
            @if($isOpening && $acc !== 'posted')
                <a href="{{ route('store-stock-adjustments.edit', $adjustment) }}" class="btn btn-sm btn-outline-primary">
                    Edit
                </a>
            @endif
        @endcan

        <a href="{{ route('store-stock-adjustments.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Type</div>
                <div class="fw-semibold">{{ ucfirst($adjustment->adjustment_type ?? '-') }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <div>
                    <span class="badge bg-secondary">
                        {{ ucfirst($adjustment->status ?? 'posted') }}
                    </span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Accounting</div>
                <div>
                    <span class="badge {{ $acc === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ ucfirst(str_replace('_',' ', $acc)) }}
                    </span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Date</div>
                <div class="fw-semibold">{{ optional($adjustment->adjustment_date)->format('d-m-Y') ?? '-' }}</div>
            </div>
        </div>

        <div class="mt-3">
            @can('store.issue.post_to_accounts')
                @if($acc !== 'posted' && $acc !== 'not_required')
                    <form method="POST" action="{{ route('store-stock-adjustments.post-to-accounts', $adjustment) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-success"
                                onclick="return confirm('Post this Stock Adjustment to Accounts?');">
                            Post to Accounts
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</div>

{{-- Lines --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="fw-semibold">Lines</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Brand</th>
                        <th class="text-end">Qty</th>
                        <th>UOM</th>
                        <th class="text-end">Unit Rate</th>
                        <th class="text-end">Amount</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($adjustment->lines as $i => $line)
                    @php
                        $rate = $line->opening_unit_rate ?? $line->unit_rate ?? null;
                        $qty  = $line->quantity ?? 0;
                        $amt  = ($rate !== null) ? ((float)$rate * (float)$qty) : null;
                    @endphp
                    <tr>
                        <td>{{ $i+1 }}</td>
                        <td>{{ $line->item->name ?? $line->item_id }}</td>
                        <td>{{ $line->brand ?? '-' }}</td>
                        <td class="text-end">{{ number_format((float)$qty, 3) }}</td>
                        <td>{{ $line->uom->name ?? '-' }}</td>
                        <td class="text-end">{{ $rate !== null ? number_format((float)$rate, 2) : '-' }}</td>
                        <td class="text-end">{{ $amt !== null ? number_format((float)$amt, 2) : '-' }}</td>
                        <td>{{ $line->remarks ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No lines found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
