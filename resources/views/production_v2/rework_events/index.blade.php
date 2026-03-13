@extends('layouts.erp')

@section('title', 'Production V2 Rework')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Rework</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.rework-events.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Rework Event
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'rework'])

    @php
        $pageRows = $rows->getCollection();
        $openCount = $pageRows->filter(fn($row) => in_array($row->final_result, ['pending', 'failed', 'reoffer', 'hold'], true))->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-warning mb-1">Open Rework</div><div class="display-6 mb-0">{{ number_format($openCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Quick Flow</div><div class="small text-body-secondary">Use rework open pages to close repair loops through re-inspection and trial assembly.</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Rework</th>
                            <th>Date</th>
                            <th>Assembly</th>
                            <th>Source Inspection</th>
                            <th>Reason</th>
                            <th>Final Result</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>RW-{{ $row->id }}</strong></td>
                            <td>{{ $row->rework_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->assembly?->assembly_code ?: '-' }}</td>
                            <td>{{ $row->sourceInspection ? ('IN-' . $row->sourceInspection->id) : '-' }}</td>
                            <td>{{ $row->reason_code ?: '-' }}</td>
                            <td>{{ $row->final_result ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.rework-events.show', ['project' => $project->id, 'reworkEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No rework events found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 pv2-mobile-list">
                @forelse($rows as $row)
                    @php $isOpen = in_array($row->final_result, ['pending', 'failed', 'reoffer', 'hold'], true); @endphp
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">RW-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->assembly?->assembly_code ?: '-' }}</div>
                            </div>
                            <span class="badge {{ $isOpen ? 'text-bg-warning' : 'text-bg-success' }}">{{ ucfirst($row->final_result ?: 'pending') }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->rework_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Source Inspection</span><span>{{ $row->sourceInspection ? ('IN-' . $row->sourceInspection->id) : '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Reason</span><span>{{ $row->reason_code ?: '-' }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.rework-events.show', ['project' => $project->id, 'reworkEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Rework</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No rework events found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} rework events
                @else
                    Showing 0 rework events
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
