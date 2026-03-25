@extends('layouts.erp')

@section('title', 'Production V2 Dispatches')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Dispatches</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.dispatches.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Dispatch
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'dispatches'])

    @php
        $pageRows = $rows->getCollection();
        $draftCount = $pageRows->where('status', 'draft')->count();
        $finalizedCount = $pageRows->where('status', 'finalized')->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-warning mb-1">Draft Dispatches</div><div class="display-6 mb-0">{{ number_format($draftCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-success mb-1">Finalized</div><div class="display-6 mb-0">{{ number_format($finalizedCount) }}</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Dispatch</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th class="text-end">Lines</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Weight</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row->dispatch_number }}</strong></td>
                            <td>{{ $row->dispatch_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->client?->name ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->lines_count) }}</td>
                            <td class="text-end">{{ number_format((float) $row->total_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $row->total_weight_kg, 3) }} kg</td>
                            <td>{{ ucfirst($row->status) }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.dispatches.show', ['project' => $project->id, 'dispatch' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No dispatches created yet.</td>
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
                                <div class="pv2-mobile-card__title">{{ $row->dispatch_number }}</div>
                                <div class="small text-body-secondary">{{ $row->client?->name ?: 'Client pending' }}</div>
                            </div>
                            <span class="badge {{ $row->status === 'finalized' ? 'text-bg-success' : ($row->status === 'cancelled' ? 'text-bg-secondary' : 'text-bg-warning') }}">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->dispatch_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Lines</span><span>{{ number_format($row->lines_count) }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Qty</span><span>{{ number_format((float) $row->total_qty, 3) }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Weight</span><span>{{ number_format((float) $row->total_weight_kg, 3) }} kg</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.dispatches.show', ['project' => $project->id, 'dispatch' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Dispatch</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No dispatches created yet.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} dispatches
                @else
                    Showing 0 dispatches
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
