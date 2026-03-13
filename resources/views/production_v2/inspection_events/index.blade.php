@extends('layouts.erp')

@section('title', 'Production V2 Inspection')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Inspection</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Inspection Event
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'inspection'])

    @php
        $pageRows = $rows->getCollection();
        $failedCount = $pageRows->filter(fn($row) => in_array($row->result, ['failed', 'reoffer', 'hold'], true))->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-danger mb-1">Needs Rework</div><div class="display-6 mb-0">{{ number_format($failedCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Quick Flow</div><div class="small text-body-secondary">Failed, re-offer, and hold results keep the next rework action visible at row level.</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Inspection</th>
                            <th>Date</th>
                            <th>Assembly</th>
                            <th>Type</th>
                            <th>Weld Ref</th>
                            <th>Result</th>
                            <th>Checked By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>IN-{{ $row->id }}</strong></td>
                            <td>{{ $row->inspection_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->assembly?->assembly_code ?: '-' }}</td>
                            <td>{{ strtoupper($row->inspection_type) }}</td>
                            <td>{{ $row->weldingEvent ? ('WE-' . $row->weldingEvent->id) : '-' }}</td>
                            <td>{{ $row->result ?: '-' }}</td>
                            <td>{{ $row->checkedBy?->name ?: '-' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    @if(in_array($row->result, ['failed', 'reoffer', 'hold'], true))
                                        <a href="{{ route('projects.production-v2.rework-events.create', ['project' => $project->id, 'inspection_event_id' => $row->id]) }}" class="btn btn-sm btn-primary">Rework</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No inspection events found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 pv2-mobile-list">
                @forelse($rows as $row)
                    @php $needsRework = in_array($row->result, ['failed', 'reoffer', 'hold'], true); @endphp
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">IN-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->assembly?->assembly_code ?: '-' }} / {{ strtoupper($row->inspection_type) }}</div>
                            </div>
                            <span class="badge {{ $needsRework ? 'text-bg-danger' : 'text-bg-success' }}">{{ ucfirst($row->result ?: 'pending') }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->inspection_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Weld Ref</span><span>{{ $row->weldingEvent ? ('WE-' . $row->weldingEvent->id) : '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Checked By</span><span>{{ $row->checkedBy?->name ?: '-' }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.inspection-events.show', ['project' => $project->id, 'inspectionEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Inspection</a>
                            @if($needsRework)
                                <a href="{{ route('projects.production-v2.rework-events.create', ['project' => $project->id, 'inspection_event_id' => $row->id]) }}" class="btn btn-sm btn-primary">Create Rework</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No inspection events found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} inspection events
                @else
                    Showing 0 inspection events
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
