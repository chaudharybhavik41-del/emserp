@extends('layouts.erp')

@section('title', 'Production Plan')

@section('content')
@php
    // project-scoped module: /projects/{project}/production-plans/{id}
    $routeProject = request()->route('project');
    $projectId = $routeProject?->id ?? (int)($plan->project_id ?? 0);
    $planId = (int)($plan->id ?? 0);
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskBoardRoute = \Illuminate\Support\Facades\Route::has('task-board.index') ? 'task-board.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-diagram-3"></i> {{ $plan->plan_number }}</h2>
            <div class="text-muted small">
                Project: {{ $plan->project?->code }} — {{ $plan->project?->name }} |
                BOM: {{ $plan->bom?->bom_number ?? ('#'.$plan->bom_id) }} |
                Status: <strong>{{ strtoupper($plan->status) }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url('/projects/'.$projectId.'/production-plans') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            @can('tasks.view')
                @if($taskIndexRoute)
                    <a href="{{ route($taskIndexRoute, ['project' => $projectId, 'bom' => $plan->bom_id, 'q' => $plan->plan_number]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-task"></i> Related Tasks
                    </a>
                @endif
                @if($taskBoardRoute)
                    <a href="{{ route($taskBoardRoute, ['project' => $projectId, 'bom' => $plan->bom_id]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-kanban"></i> Task Board
                    </a>
                @endif
            @endcan

            @can('tasks.create')
                @if($taskCreateRoute)
                    <a href="{{ route($taskCreateRoute, ['project' => $projectId, 'bom' => $plan->bom_id, 'title' => 'Production Plan '. $plan->plan_number .' follow-up', 'description' => 'Linked from Production Plan '. $plan->plan_number]) }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Add Task
                    </a>
                @endif
            @endcan

            @if($plan->status === 'draft')
                @can('production.plan.update')
                    <a href="{{ url('/projects/'.$projectId.'/production-plans/'.$planId.'/route-matrix') }}" class="btn btn-outline-primary">
                        <i class="bi bi-grid-3x3-gap"></i> Route Matrix
                    </a>
                @endcan
            @endif

            @if($plan->status === 'draft')
                @can('production.plan.approve')
                    <form method="POST" action="{{ url('/projects/'.$projectId.'/production-plans/'.$planId.'/approve') }}" onsubmit="return confirm('Approve this plan?');">
                        @csrf
                        <button class="btn btn-success">
                            <i class="bi bi-check2-circle"></i> Approve
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Plan Summary</h6>
                    <div class="small text-muted">Items total: <strong>{{ $stats['items_total'] ?? 0 }}</strong></div>
                    <div class="small text-muted">Pending: <strong>{{ $stats['items_pending'] ?? 0 }}</strong></div>
                    <div class="small text-muted">In progress: <strong>{{ $stats['items_in_progress'] ?? 0 }}</strong></div>
                    <div class="small text-muted">Done: <strong>{{ $stats['items_done'] ?? 0 }}</strong></div>

                    <hr>
                    <div class="small text-muted">Routing can be edited per plan item, or bulk via <strong>Route Matrix</strong>.</div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Plan Items</h6>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Mark / Code</th>
                                    <th>Description</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Weight (kg)</th>
                                    <th>Status</th>
                                    <th class="text-end">Route</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $it)
                                    @php
                                        $itemId = (int)($it->id ?? 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            @if(($it->item_type ?? '') === 'assembly')
                                                <span class="badge bg-info">Assembly</span>
                                            @else
                                                <span class="badge bg-secondary">Part</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(($it->item_type ?? '') === 'assembly')
                                                <strong>{{ $it->assembly_mark }}</strong>
                                                <div class="small text-muted">{{ $it->assembly_type }}</div>
                                            @else
                                                {{ $it->item_code ?? ('#'.$itemId) }}
                                                @if(!empty($it->assembly_mark))
                                                    <div class="small text-muted">Asm: {{ $it->assembly_mark }}</div>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ $it->description }}</td>
                                        <td class="text-end">{{ number_format((float)($it->planned_qty ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float)($it->planned_weight_kg ?? 0), 3) }}</td>
                                        <td><span class="badge bg-light text-dark">{{ $it->status }}</span></td>
                                        <td class="text-end">
                                            @if($plan->status === 'draft')
                                                <a class="btn btn-sm btn-outline-primary"
                                                   href="{{ url('/projects/'.$projectId.'/production-plans/'.$planId.'/items/'.$itemId.'/route') }}">
                                                   Route
                                                </a>
                                            @else
                                                <span class="text-muted small">Locked</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">No items imported.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
