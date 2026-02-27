@extends('layouts.erp')

@section('content')
@php
    $taskIndexRoute = \Illuminate\Support\Facades\Route::has('tasks.index') ? 'tasks.index' : null;
    $taskBoardRoute = \Illuminate\Support\Facades\Route::has('task-board.index') ? 'task-board.index' : null;
    $taskCreateRoute = \Illuminate\Support\Facades\Route::has('tasks.create') ? 'tasks.create' : null;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">BOM {{ $bom->bom_number }}</h4>
        <small class="text-muted">
            Project: {{ $project->code }} - {{ $project->name }}
        </small>
    </div>
    <div class="text-end">
        <a href="{{ route('projects.boms.index', $project) }}" class="btn btn-outline-secondary">
            Back to BOM List
        </a>

        <a href="{{ route('projects.boms.export', [$project, $bom]) }}"
           class="btn btn-outline-success">
            Export CSV
        </a>
		<a href="{{ route('projects.boms.requirements', [$project, $bom]) }}"
  		 class="btn btn-outline-dark">
  		  Requirements
		</a>
		<a href="{{ route('projects.boms.material-planning.index', [$project, $bom]) }}"
 		  class="btn btn-outline-primary">
 		   Material Planning
		</a>
      
		<a href="{{ route('projects.boms.section-plans.index', [$project, $bom]) }}"
 		  class="btn btn-outline-primary">
 		   Section Planning
		</a>
      
    	  <a href="{{ route('projects.boms.purchase-plates.index', [$project, $bom]) }}"
  		 class="btn btn-outline-primary">
   		 Plate Purchase List
		</a>
        @can('project.bom.create')
            <form action="{{ route('projects.boms.clone-version', [$project, $bom]) }}"
                  method="POST"
                  class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary">
                    Clone as New Version
                </button>
            </form>

            <a href="{{ route('projects.boms.copy-form', [$project, $bom]) }}"
               class="btn btn-outline-info">
                Copy to Another Project
            </a>
        @endcan
		@can('project.bom_template.create')
    		<form action="{{ route('projects.boms.save-template', [$project, $bom]) }}"
          method="POST"
          class="d-inline"
          onsubmit="return confirm('Save this BOM as a template in the library?');">
        @csrf
        <button type="submit" class="btn btn-outline-warning btn-sm">
            Save as Template
        </button>
   		 </form>
		@endcan
        @can('project.bom.update')
            @if($bom->isDraft())
                <a href="{{ route('projects.boms.edit', [$project, $bom]) }}"
                   class="btn btn-outline-primary">
                    Edit BOM Header
                </a>
            @endif
        @endcan

        @can('project.bom.finalize')
            @if($bom->isDraft())
                <form action="{{ route('projects.boms.finalize', [$project, $bom]) }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Finalize this BOM? It will become read-only.');">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        Finalize BOM
                    </button>
                </form>
            @endif
        @endcan

        @can('tasks.view')
            @if($taskIndexRoute)
                <a href="{{ route($taskIndexRoute, ['project' => $project->id, 'bom' => $bom->id]) }}" class="btn btn-outline-secondary">
                    BOM Tasks
                </a>
            @endif
            @if($taskBoardRoute)
                <a href="{{ route($taskBoardRoute, ['project' => $project->id, 'bom' => $bom->id]) }}" class="btn btn-outline-secondary">
                    Task Board
                </a>
            @endif
        @endcan
        @can('tasks.create')
            @if($taskCreateRoute)
                <a href="{{ route($taskCreateRoute, ['project' => $project->id, 'bom' => $bom->id, 'title' => 'BOM '. $bom->bom_number .' follow-up']) }}" class="btn btn-outline-primary">
                    Add BOM Task
                </a>
            @endif
        @endcan
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        BOM Summary
    </div>
    <div class="card-body">
        <div class="row mb-2">
            <div class="col-md-3">
                <strong>BOM No:</strong> {{ $bom->bom_number }}
            </div>
            <div class="col-md-2">
                <strong>Version:</strong> {{ $bom->version }}
            </div>
            <div class="col-md-2">
                <strong>Status:</strong> {{ ucfirst($bom->status->value) }}
            </div>
            <div class="col-md-3">
                <strong>Total Weight:</strong> {{ $bom->total_weight }} kg
            </div>
            <div class="col-md-2">
                <strong>Finalized:</strong>
                @if($bom->finalized_date)
                    {{ $bom->finalized_date->format('d-m-Y') }} by {{ $bom->finalizedBy?->name }}
                @else
                    <span class="text-muted">Not finalized</span>
                @endif
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-md-12">
                <strong>Remarks:</strong>
                {{ $bom->metadata['remarks'] ?? '-' }}
            </div>
        </div>

        @if(!empty($categorySummary))
            <hr>
            <h6>Category-wise Summary (leaf materials)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Material Category</th>
                            <th>Lines</th>
                            <th>Total Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categorySummary as $cat => $row)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $cat)) }}</td>
                                <td>{{ $row['lines'] }}</td>
                                <td>{{ number_format($row['total_weight'], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>BOM Task Snapshot</span>
        @if($taskIndexRoute && auth()->user()->can('tasks.view'))
            <a href="{{ route($taskIndexRoute, ['project' => $project->id, 'bom' => $bom->id]) }}" class="btn btn-sm btn-outline-secondary">View All</a>
        @endif
    </div>
    <div class="card-body">
        @can('tasks.view')
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <small class="text-muted d-block">Total</small>
                        <strong>{{ $taskStats['total'] ?? 0 }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <small class="text-muted d-block">Open</small>
                        <strong class="text-warning">{{ $taskStats['open'] ?? 0 }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <small class="text-muted d-block">Completed</small>
                        <strong class="text-success">{{ $taskStats['completed'] ?? 0 }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <small class="text-muted d-block">Overdue</small>
                        <strong class="text-danger">{{ $taskStats['overdue'] ?? 0 }}</strong>
                    </div>
                </div>
            </div>

            @if($recentTasks->isEmpty())
                <div class="text-muted small">No tasks are linked with this BOM.</div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($recentTasks as $task)
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    @if(\Illuminate\Support\Facades\Route::has('tasks.show'))
                                        <a class="text-decoration-none fw-medium" href="{{ route('tasks.show', $task) }}">
                                            {{ $task->task_number }} - {{ $task->title }}
                                        </a>
                                    @else
                                        <span class="fw-medium">{{ $task->task_number }} - {{ $task->title }}</span>
                                    @endif
                                    <div class="small text-muted">
                                        {{ $task->assignee?->name ?? 'Unassigned' }}
                                        @if($task->due_date)
                                            | Due {{ $task->due_date->format('d M Y') }}
                                        @endif
                                    </div>
                                </div>
                                @if($task->status)
                                    <span class="badge" style="background-color: {{ $task->status->color }}">{{ $task->status->name }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="text-muted small">Task details are hidden due to permission settings.</div>
        @endcan
    </div>
</div>

@include('projects.boms.partials._items_table', [
    'project' => $project,
    'bom' => $bom,
    'assemblyWeights' => $assemblyWeights,
])
@endsection
