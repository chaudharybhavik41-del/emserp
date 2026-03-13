@extends('layouts.erp')

@section('title', 'Production V2 Cut Batches')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Cut Batches</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.cut-batches.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Cut Batch
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'cut_batches'])

    @php
        $pageRows = $rows->getCollection();
        $approvedCount = $pageRows->where('status', 'approved')->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Approved Batches</div><div class="display-6 mb-0">{{ number_format($approvedCount) }}</div></div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Next Step</div><div class="small text-body-secondary">Open a cut batch and move directly to fit-up after verifying WIP output.</div></div></div>
        </div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Batch</th>
                            <th>Date</th>
                            <th>Cutting Plan</th>
                            <th>Mother Stock</th>
                            <th class="text-end">WIP Items</th>
                            <th>Store Issue</th>
                            <th class="text-end">Remnants</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php
                            $motherStockLabel = $row->motherStock?->plate_number
                                ?: ($row->motherStock?->section_profile ?: ($row->motherStock ? 'Stock #' . $row->motherStock->id : '-'));
                        @endphp
                        <tr>
                            <td><strong>CB-{{ $row->id }}</strong></td>
                            <td>{{ $row->cut_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->cuttingPlan?->plan_number ?: '-' }}</td>
                            <td>{{ $motherStockLabel }}</td>
                            <td class="text-end">{{ number_format($row->wip_items_count) }}</td>
                            <td>{{ $row->storeIssue?->issue_number ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->remnants_count) }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.cut-batches.show', ['project' => $project->id, 'cutBatch' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No cut batches found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 pv2-mobile-list">
                @forelse($rows as $row)
                    @php
                        $motherStockLabel = $row->motherStock?->plate_number
                            ?: ($row->motherStock?->section_profile ?: ($row->motherStock ? 'Stock #' . $row->motherStock->id : '-'));
                    @endphp
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">CB-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->cuttingPlan?->plan_number ?: 'No plan' }}</div>
                            </div>
                            <span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->cut_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Mother Stock</span><span>{{ $motherStockLabel }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">WIP</span><span>{{ number_format($row->wip_items_count) }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Remnants</span><span>{{ number_format($row->remnants_count) }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.cut-batches.show', ['project' => $project->id, 'cutBatch' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Batch</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No cut batches found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} cut batches
                @else
                    Showing 0 cut batches
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
