@extends('layouts.erp')

@section('title', 'Production DPRs')

@php
    $selectedProject = isset($projects) ? $projects->firstWhere('id', (int) $projectId) : null;
    $isProjectScoped = (bool) request()->route('project');

    $draftInPage = $rows->getCollection()->where('status', 'draft')->count();
    $submittedInPage = $rows->getCollection()->where('status', 'submitted')->count();
    $approvedInPage = $rows->getCollection()->where('status', 'approved')->count();
    $cancelledInPage = $rows->getCollection()->where('status', 'cancelled')->count();
@endphp

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production DPR</h1>
        <div class="small text-body-secondary">
            @if($selectedProject)
                Project: <strong>{{ $selectedProject->code }}</strong> - {{ $selectedProject->name }}
            @elseif($isProjectScoped)
                Project selected
            @else
                Select project and manage daily production records.
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @if(\Illuminate\Support\Facades\Route::has('production.workbench'))
            <a href="{{ route('production.workbench') }}" class="btn btn-sm btn-outline-secondary">Workbench</a>
        @endif
        @can('production.dpr.create')
            <a href="{{ route('production.production-dprs.create', (int)$projectId > 0 ? ['project_id' => (int)$projectId] : []) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New DPR
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @if(!$isProjectScoped && (int)$projectId <= 0)
        <div class="alert alert-info">
            Select a project from filters to create DPR and open project-specific records quickly.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-3">
            <div class="small text-uppercase text-body-secondary mb-1">Total Results</div>
            <div class="h4 mb-0">{{ number_format($rows->total()) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-3">
            <div class="small text-uppercase text-body-secondary mb-1">Draft</div>
            <div class="h4 mb-0">{{ number_format($draftInPage) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-3">
            <div class="small text-uppercase text-body-secondary mb-1">Submitted</div>
            <div class="h4 mb-0">{{ number_format($submittedInPage) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-3">
            <div class="small text-uppercase text-body-secondary mb-1">Approved</div>
            <div class="h4 mb-0">{{ number_format($approvedInPage) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-3">
            <div class="small text-uppercase text-body-secondary mb-1">Cancelled</div>
            <div class="h4 mb-0">{{ number_format($cancelledInPage) }}</div>
        </div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
                @if($isProjectScoped)
                    <input type="hidden" name="project_id" value="{{ (int) $projectId }}">
                @else
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label mb-1" for="project_id">Project</label>
                        <select id="project_id" name="project_id" class="form-select form-select-sm" data-erp-select data-placeholder="Select project" data-allow-clear="1">
                            <option value="">All Projects</option>
                            @foreach(($projects ?? collect()) as $proj)
                                <option value="{{ $proj->id }}" @selected((string)request('project_id', $projectId) === (string)$proj->id)>
                                    {{ $proj->code }} - {{ $proj->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label mb-1" for="q">Search</label>
                    <input type="text" id="q" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Plan, activity, worker">
                </div>

                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label mb-1" for="production_plan_id">Plan</label>
                    <select id="production_plan_id" name="production_plan_id" class="form-select form-select-sm" data-erp-select data-placeholder="All plans" data-allow-clear="1">
                        <option value="">All Plans</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) request('production_plan_id') === (string) $plan->id)>
                                {{ $plan->plan_number }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label mb-1" for="production_activity_id">Activity</label>
                    <select id="production_activity_id" name="production_activity_id" class="form-select form-select-sm" data-erp-select data-placeholder="All activities" data-allow-clear="1">
                        <option value="">All Activities</option>
                        @foreach($activities as $activity)
                            <option value="{{ $activity->id }}" @selected((string) request('production_activity_id') === (string) $activity->id)>
                                {{ $activity->code }} - {{ $activity->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-4 col-lg-1">
                    <label class="form-label mb-1" for="status">Status</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                    </select>
                </div>

                <div class="col-6 col-md-4 col-lg-1">
                    <label class="form-label mb-1" for="date_from">From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
                </div>

                <div class="col-6 col-md-4 col-lg-1">
                    <label class="form-label mb-1" for="date_to">To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
                </div>

                <div class="col-6 col-md-4 col-lg-1">
                    <label class="form-label mb-1" for="per_page">Rows</label>
                    <select id="per_page" name="per_page" class="form-select form-select-sm">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">Apply</button>
                    <a href="{{ request()->url() }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-md-none">
        @forelse($rows as $row)
            @php
                $openProjectId = (int)($row->project_id ?: $projectId);
            @endphp
            <div class="card mb-2">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-semibold">DPR #{{ $row->id }}</div>
                            <div class="small text-body-secondary">{{ $row->dpr_date }} | {{ $row->plan_number }}</div>
                        </div>
                        <span class="badge text-bg-light text-uppercase">{{ $row->status }}</span>
                    </div>
                    @if(!$isProjectScoped)
                        <div class="small mb-1"><strong>Project:</strong> {{ $row->project_code }} - {{ $row->project_name }}</div>
                    @endif
                    <div class="small mb-1"><strong>Activity:</strong> {{ $row->activity_name }} ({{ $row->activity_code }})</div>
                    <div class="small mb-2"><strong>Shift:</strong> {{ $row->shift ?? '-' }} | <strong>Worker:</strong> {{ $row->worker_name ?? '-' }}</div>
                    @if($openProjectId > 0)
                        <a class="btn btn-sm btn-outline-primary w-100" href="{{ url('/projects/'.$openProjectId.'/production-dprs/'.$row->id) }}">Open DPR</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-muted py-4">No DPRs found.</div></div>
        @endforelse
    </div>

    <div class="card d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 7%">ID</th>
                        <th style="width: 10%">Date</th>
                        @if(!$isProjectScoped)
                            <th style="width: 15%">Project</th>
                        @endif
                        <th style="width: 12%">Plan</th>
                        <th style="width: 22%">Activity</th>
                        <th style="width: 8%">Shift</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 15%">Worker</th>
                        <th style="width: 8%" class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php
                            $openProjectId = (int)($row->project_id ?: $projectId);
                        @endphp
                        <tr>
                            <td>#{{ $row->id }}</td>
                            <td>{{ $row->dpr_date }}</td>
                            @if(!$isProjectScoped)
                                <td>{{ $row->project_code }} - {{ $row->project_name }}</td>
                            @endif
                            <td>{{ $row->plan_number }}</td>
                            <td>{{ $row->activity_name }} <span class="text-muted small">({{ $row->activity_code }})</span></td>
                            <td>{{ $row->shift ?? '-' }}</td>
                            <td><span class="badge text-bg-light text-uppercase">{{ $row->status }}</span></td>
                            <td>{{ $row->worker_name ?? '-' }}</td>
                            <td class="text-end">
                                @if($openProjectId > 0)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ url('/projects/'.$openProjectId.'/production-dprs/'.$row->id) }}">Open</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isProjectScoped ? 8 : 9 }}" class="text-center text-muted py-4">No DPRs found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <small class="text-body-secondary">
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} DPR rows
                @else
                    Showing 0 DPR rows
                @endif
            </small>
            @if($rows->hasPages())
                <div>{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
@endsection
