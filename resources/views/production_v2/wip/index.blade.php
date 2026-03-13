@extends('layouts.erp')

@section('title', 'Production V2 WIP Pool')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 WIP Pool</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'wip'])

    @php
        $pageRows = $rows->getCollection();
        $availableCount = $pageRows->where('status', 'available')->count();
        $consumedCount = $pageRows->where('status', 'consumed')->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-success mb-1">Available WIP</div><div class="display-6 mb-0">{{ number_format($availableCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Consumed WIP</div><div class="display-6 mb-0">{{ number_format($consumedCount) }}</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                    <label class="form-label mb-1" for="q">Search</label>
                    <input id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Piece, lot, plate, heat, MTC">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1" for="status">Status</label>
                    <select id="status" name="status" class="form-select" data-erp-select data-placeholder="All" data-allow-clear="1">
                        <option value="">All</option>
                        @foreach(['available', 'reserved', 'consumed', 'scrap', 'hold'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-primary w-100">Apply</button>
                    <a href="{{ request()->url() }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Piece / Lot</th>
                            <th>Part</th>
                            <th class="text-end">Qty</th>
                            <th>Plate</th>
                            <th>Heat</th>
                            <th>MTC</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->piece_no ?: ($row->lot_no ?: '-') }}</td>
                            <td>{{ $row->partDefinition?->part_code }}<div class="small text-body-secondary">{{ $row->partDefinition?->part_name }}</div></td>
                            <td class="text-end">{{ number_format((float) $row->qty, 3) }} {{ $row->uom?->code }}</td>
                            <td>{{ $row->plate_number ?: '-' }}</td>
                            <td>{{ $row->heat_number ?: '-' }}</td>
                            <td>{{ $row->mtc_number ?: '-' }}</td>
                            <td>{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No WIP items found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 pv2-mobile-list">
                @forelse($rows as $row)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">{{ $row->piece_no ?: ($row->lot_no ?: 'WIP#' . $row->id) }}</div>
                                <div class="small text-body-secondary">{{ $row->partDefinition?->part_code }} / {{ $row->partDefinition?->part_name }}</div>
                            </div>
                            <span class="badge {{ $row->status === 'available' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Qty</span><span>{{ number_format((float) $row->qty, 3) }} {{ $row->uom?->code }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Plate</span><span>{{ $row->plate_number ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Heat</span><span>{{ $row->heat_number ?: '-' }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No WIP items found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} WIP rows
                @else
                    Showing 0 WIP rows
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
