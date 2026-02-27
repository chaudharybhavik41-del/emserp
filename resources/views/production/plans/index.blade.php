@extends('layouts.erp')

@section('title', 'Production Plans')

@section('content')
@php
    $routeProject = request()->route('project');
    // If route param is numeric, $routeProject is "1" (string). If model-bound, it's an object.
    $currentProjectId = is_object($routeProject) ? (int)($routeProject->id ?? 0) : (int)($routeProject ?? 0);
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-diagram-3"></i> Production Plans</h1>

        @can('production.plan.create')
            @if($currentProjectId > 0)
                <a href="{{ url('/projects/'.$currentProjectId.'/production-plans/from-bom') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Create from BOM
                </a>
            @else
                <span class="text-muted small">Open a project first to create plan from BOM.</span>
            @endif
        @endcan

        @if($currentProjectId > 0)
            @can('tasks.view')
                @if($taskIndexRoute)
                    <a href="{{ route($taskIndexRoute, ['project' => $currentProjectId]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-task"></i> Project Tasks
                    </a>
                @endif
            @endcan
            @can('tasks.create')
                @if($taskCreateRoute)
                    <a href="{{ route($taskCreateRoute, ['project' => $currentProjectId, 'title' => 'Production planning follow-up']) }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Add Task
                    </a>
                @endif
            @endcan
        @endif
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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Plan No</th>
                            <th>Project</th>
                            <th>BOM</th>
                            <th>Status</th>
                            <th class="text-muted">Approved</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $p)
                            <tr>
                                <td><strong>{{ $p->plan_number }}</strong></td>
                                <td>{{ $p->project?->code }} — {{ $p->project?->name }}</td>
                                <td>{{ $p->bom?->bom_number ?? ('#'.$p->bom_id) }}</td>
                                <td>
                                    @if($p->status === 'approved')
                                        <span class="badge bg-success">Approved</span>
                                    @elseif($p->status === 'cancelled')
                                        <span class="badge bg-secondary">Cancelled</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Draft</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if($p->approved_at)
                                        {{ $p->approved_at->format('Y-m-d H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($currentProjectId > 0)
                                        <a href="{{ url('/projects/'.$currentProjectId.'/production-plans/'.$p->id) }}" class="btn btn-sm btn-outline-primary">
                                            Open
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No production plans found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($plans->hasPages())
                <div class="mt-2">{{ $plans->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
