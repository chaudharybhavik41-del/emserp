@extends('layouts.erp')

@section('title', 'Production Plans')

@section('page_header')
@php
    $routeProject = request()->route('project');
    $currentProjectId = is_object($routeProject) ? (int)($routeProject->id ?? 0) : (int)($routeProject ?? 0);
    if ($currentProjectId <= 0) {
        $currentProjectId = (int) request('project_id', 0);
    }
@endphp
    <div>
        <h1 class="h4 mb-1">Production Plans</h1>
        <div class="small text-body-secondary">Plan execution generated from BOM with routing, approval, and downstream DPR linkage.</div>
    </div>

    @can('production.plan.create')
        @if($currentProjectId > 0)
            <a href="{{ url('/projects/'.$currentProjectId.'/production-plans/from-bom') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Create from BOM
            </a>
        @else
            <span class="text-muted small">Open a project first to create plan from BOM.</span>
        @endif
    @endcan
@endsection

@section('content')
@php
    $draftInPage = $plans->getCollection()->where('status', 'draft')->count();
    $approvedInPage = $plans->getCollection()->where('status', 'approved')->count();
    $cancelledInPage = $plans->getCollection()->where('status', 'cancelled')->count();
@endphp

<div class="row g-3 mb-3">
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Total Results</div>
        <div class="h4 mb-0">{{ number_format($plans->total()) }}</div>
    </div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Draft In Page</div>
        <div class="h4 mb-0">{{ number_format($draftInPage) }}</div>
    </div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Approved In Page</div>
        <div class="h4 mb-0">{{ number_format($approvedInPage) }}</div>
    </div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body py-3">
        <div class="small text-uppercase text-body-secondary mb-1">Cancelled In Page</div>
        <div class="h4 mb-0">{{ number_format($cancelledInPage) }}</div>
    </div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ request()->url() }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label mb-1" for="q">Search</label>
                <input type="text" id="q" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Plan/project/BOM">
            </div>

            @if(!request()->route('project'))
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1" for="project_id">Project</label>
                    <select id="project_id" name="project_id" class="form-select form-select-sm" data-erp-select data-placeholder="All projects" data-allow-clear="1">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((int) request('project_id', 0) === (int) $project->id)>
                                {{ $project->code }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-6 col-md-2">
                <label class="form-label mb-1" for="status">Status</label>
                <select id="status" name="status" class="form-select form-select-sm" data-erp-select data-placeholder="All" data-allow-clear="1">
                    <option value="">All</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label mb-1" for="bom_id">BOM</label>
                <select id="bom_id" name="bom_id" class="form-select form-select-sm" data-erp-select data-placeholder="All BOMs" data-allow-clear="1">
                    <option value="">All BOMs</option>
                    @foreach($boms as $bom)
                        <option value="{{ $bom->id }}" @selected((string) request('bom_id') === (string) $bom->id)>
                            {{ $bom->bom_number ?? ('#'.$bom->id) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="sort">Sort</label>
                <select id="sort" name="sort" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="" @selected(request('sort', '') === '')>Latest</option>
                    <option value="plan_number" @selected(request('sort') === 'plan_number')>Plan #</option>
                    <option value="status" @selected(request('sort') === 'status')>Status</option>
                    <option value="approved_at" @selected(request('sort') === 'approved_at')>Approved At</option>
                    <option value="created_at" @selected(request('sort') === 'created_at')>Created</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="dir">Dir</label>
                <select id="dir" name="dir" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    <option value="desc" @selected(request('dir', 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(request('dir') === 'asc')>Asc</option>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="per_page">Rows</label>
                <select id="per_page" name="per_page" class="form-select form-select-sm" data-erp-select data-hide-search="true">
                    @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="{{ request()->url() }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width: 14%">Plan No</th>
                    <th style="width: 26%">Project</th>
                    <th style="width: 18%">BOM</th>
                    <th style="width: 12%">Status</th>
                    <th style="width: 18%">Approved</th>
                    <th style="width: 12%" class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td><strong>{{ $plan->plan_number }}</strong></td>
                        <td>{{ $plan->project?->code }} - {{ $plan->project?->name }}</td>
                        <td>{{ $plan->bom?->bom_number ?? ('#' . $plan->bom_id) }}</td>
                        <td>
                            @if($plan->status === 'approved')
                                <span class="badge text-bg-success">Approved</span>
                            @elseif($plan->status === 'cancelled')
                                <span class="badge text-bg-secondary">Cancelled</span>
                            @else
                                <span class="badge text-bg-warning text-dark">Draft</span>
                            @endif
                        </td>
                        <td class="small text-body-secondary">
                            @if($plan->approved_at)
                                {{ $plan->approved_at->format('Y-m-d H:i') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            @php
                                $openProjectId = (int) ($plan->project_id ?? $currentProjectId);
                            @endphp
                            @if($openProjectId > 0)
                                <a href="{{ url('/projects/'.$openProjectId.'/production-plans/'.$plan->id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No production plans found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <small class="text-body-secondary">
            @if($plans->total() > 0)
                Showing {{ $plans->firstItem() }} to {{ $plans->lastItem() }} of {{ $plans->total() }} plans
            @else
                Showing 0 plans
            @endif
        </small>
        @if($plans->hasPages())
            <div>{{ $plans->links() }}</div>
        @endif
    </div>
</div>
@endsection
