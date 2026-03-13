@extends('layouts.erp')

@section('title', 'Production V2 Welding')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Welding</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <a href="{{ route('projects.production-v2.welding-events.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Welding Event
    </a>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'welding'])

    @php
        $pageRows = $rows->getCollection();
        $attentionCount = $pageRows->where('inspections_count', 0)->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-warning mb-1">Awaiting Inspection</div><div class="display-6 mb-0">{{ number_format($attentionCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Quick Flow</div><div class="small text-body-secondary">Open a weld row and raise inspection directly when shop-floor welding is complete.</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Weld</th>
                            <th>Date</th>
                            <th>Assembly</th>
                            <th>Process</th>
                            <th>Welder</th>
                            <th class="text-end">Inspections</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>WE-{{ $row->id }}</strong></td>
                            <td>{{ $row->weld_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->assembly?->assembly_code ?: '-' }}</td>
                            <td>{{ $row->welding_process }}</td>
                            <td>{{ $row->welder?->name ?: '-' }}</td>
                            <td class="text-end">{{ number_format($row->inspections_count) }}</td>
                            <td>{{ $row->status }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                    <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id, 'assembly_id' => $row->assembly_id, 'welding_event_id' => $row->id]) }}" class="btn btn-sm btn-primary">Inspect</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No welding events found.</td>
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
                                <div class="pv2-mobile-card__title">WE-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->assembly?->assembly_code ?: '-' }} / {{ $row->welding_process }}</div>
                            </div>
                            <span class="badge {{ $row->inspections_count > 0 ? 'text-bg-success' : 'text-bg-warning' }}">{{ $row->inspections_count > 0 ? 'Inspected' : 'Pending QC' }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->weld_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Welder</span><span>{{ $row->welder?->name ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Inspections</span><span>{{ number_format($row->inspections_count) }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.welding-events.show', ['project' => $project->id, 'weldingEvent' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open Weld</a>
                            <a href="{{ route('projects.production-v2.inspection-events.create', ['project' => $project->id, 'assembly_id' => $row->assembly_id, 'welding_event_id' => $row->id]) }}" class="btn btn-sm btn-primary">Create Inspection</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No welding events found.</div>
                @endforelse
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} welding events
                @else
                    Showing 0 welding events
                @endif
            </small>
            {{ $rows->links() }}
        </div>
    </div>
@endsection
