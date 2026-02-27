@extends('layouts.erp')

@section('title', 'Production DPRs')

@section('content')
@php
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
    $isScoped = !empty($projectId);
    $indexUrl = $isScoped
        ? url('/projects/'.$projectId.'/production-dprs')
        : url('/production/production-dprs');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><i class="bi bi-clipboard2-check"></i> Production DPRs</h1>
            <div class="text-muted small">
                {{ $isScoped ? 'Project view' : 'All projects view' }}
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('tasks.view')
                @if($taskIndexRoute && $isScoped)
                    <a href="{{ route($taskIndexRoute, ['project' => $projectId]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-task"></i> Project Tasks
                    </a>
                @endif
            @endcan
            @can('tasks.create')
                @if($taskCreateRoute && $isScoped)
                    <a href="{{ route($taskCreateRoute, ['project' => $projectId, 'title' => 'DPR follow-up']) }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Add Task
                    </a>
                @endif
            @endcan
            @can('production.dpr.create')
                @php
                    $createUrl = $isScoped
                        ? url('/projects/'.$projectId.'/production-dprs/create')
                        : url('/production/production-dprs/create');
                @endphp
                <a href="{{ $createUrl }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New DPR
                </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(! $isScoped)
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ $indexUrl }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Project</label>
                        <select name="project_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Projects</option>
                            @foreach(($projects ?? collect()) as $p)
                                <option value="{{ $p->id }}" {{ (string)($projectId ?? '') === (string)$p->id ? 'selected' : '' }}>
                                    {{ $p->code }} — {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ $indexUrl }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Date</th>
                            <th>Plan</th>
                            <th>Activity</th>
                            <th>Shift</th>
                            <th>Status</th>
                            <th>Contractor</th>
                            <th>Worker</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            @php
                                $rowProjectId = (int) ($r->project_id ?? 0);
                                $showUrl = $rowProjectId > 0
                                    ? url('/projects/'.$rowProjectId.'/production-dprs/'.$r->id)
                                    : url('/production/production-dprs/'.$r->id);
                            @endphp
                            <tr>
                                <td>
                                    {{ $r->project_code ?? ('#'.$rowProjectId) }}
                                    <div class="text-muted small">{{ $r->project_name ?? '' }}</div>
                                </td>
                                <td>{{ $r->dpr_date }}</td>
                                <td>{{ $r->plan_number }}</td>
                                <td>{{ $r->activity_name }} <span class="text-muted small">({{ $r->activity_code }})</span></td>
                                <td>{{ $r->shift ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark">{{ $r->status }}</span></td>
                                <td>{{ $r->contractor_name ?? '—' }}</td>
                                <td>{{ $r->worker_name ?? '—' }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $showUrl }}">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">No DPRs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="mt-2">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
