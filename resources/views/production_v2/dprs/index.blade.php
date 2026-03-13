@extends('layouts.erp')

@section('title', 'Production V2 DPR')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Daily DPR</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('projects.production-v2.dprs.create', ['project' => $project->id]) }}" class="btn btn-sm btn-primary">Create DPR</a>
        <a href="{{ route('production-v2.project', ['project' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">Workbench</a>
    </div>
@endsection

@section('content')
    @include('production_v2.partials.module_nav', ['active' => 'dprs'])

    @php
        $pageRows = $rows->getCollection();
        $approvedCount = $pageRows->where('status', 'approved')->count();
        $draftCount = $pageRows->where('status', 'draft')->count();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-body-secondary mb-1">Current Page</div><div class="display-6 mb-0">{{ number_format($pageRows->count()) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-success mb-1">Approved</div><div class="display-6 mb-0">{{ number_format($approvedCount) }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-uppercase text-warning mb-1">Draft</div><div class="display-6 mb-0">{{ number_format($draftCount) }}</div></div></div></div>
    </div>

    @include('production_v2.partials.mobile_list_styles')

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label">Activity</label>
                    <select name="activity" class="form-select" data-erp-select data-allow-clear="1">
                        <option value="">All activities</option>
                        @foreach($activityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('activity') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" data-erp-select data-allow-clear="1">
                        <option value="">All statuses</option>
                        @foreach($statusOptions as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Apply</button>
                    <a href="{{ request()->url() }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive pv2-mobile-table">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>DPR</th>
                            <th>Date</th>
                            <th>Activity</th>
                            <th>Shift</th>
                            <th>Worker</th>
                            <th>Machine</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $row->id]) }}">DPR-{{ $row->id }}</a></td>
                            <td>{{ $row->dpr_date?->format('Y-m-d') ?: '-' }}</td>
                            <td>{{ $row->activity?->name ?: '-' }}</td>
                            <td>{{ $row->shift ?: '-' }}</td>
                            <td>{{ $row->worker?->name ?: '-' }}</td>
                            <td>{{ $row->machine?->name ?: '-' }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No Production V2 DPR created yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 pv2-mobile-list">
                @forelse($rows as $row)
                    <div class="pv2-mobile-card">
                        <div class="pv2-mobile-card__head">
                            <div>
                                <div class="pv2-mobile-card__title">DPR-{{ $row->id }}</div>
                                <div class="small text-body-secondary">{{ $row->activity?->name ?: '-' }}</div>
                            </div>
                            <span class="badge {{ $row->status === 'approved' ? 'text-bg-success' : ($row->status === 'draft' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ ucfirst($row->status) }}</span>
                        </div>
                        <div class="pv2-mobile-card__meta">
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Date</span><span>{{ $row->dpr_date?->format('Y-m-d') ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Shift</span><span>{{ $row->shift ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Worker</span><span>{{ $row->worker?->name ?: '-' }}</span></div>
                            <div class="pv2-mobile-card__row"><span class="pv2-mobile-card__label">Machine</span><span>{{ $row->machine?->name ?: '-' }}</span></div>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('projects.production-v2.dprs.show', ['project' => $project->id, 'productionDpr' => $row->id]) }}" class="btn btn-sm btn-outline-primary">Open DPR</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No Production V2 DPR created yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $rows->links() }}
    </div>
@endsection
