@extends('layouts.erp')

@section('title', 'Projects')

@section('page_header')
    <div>
        <h1 class="h5 mb-0">Projects</h1>
        <small class="text-muted">List of all projects in the system.</small>
    </div>

    @can('project.project.create')
        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Project
        </a>
    @endcan
@endsection

@section('content')
    <form method="GET" action="{{ route('projects.index') }}" class="erp-filter-bar">
        <div class="flex-grow-1" style="min-width: min(100%, 320px);">
            <label for="q" class="form-label small mb-1">Search</label>
            <input type="text"
                   name="q"
                   id="q"
                   value="{{ request('q') }}"
                   class="form-control"
                   placeholder="Search by code or name">
        </div>

        <div class="d-flex align-items-end gap-2 ms-sm-auto">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            @if(request()->has('q') && request('q') !== null && request('q') !== '')
                <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <div class="erp-table-card">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 120px;">Code</th>
                    <th>Name</th>
                    <th>Client</th>
                    <th style="width: 160px;">Created</th>
                    <th class="text-end" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($projects as $project)
                <tr>
                    <td class="fw-semibold">
                        <a href="{{ route('projects.show', $project) }}" class="text-decoration-none">
                            {{ $project->code }}
                        </a>
                    </td>
                    <td>
                        {{ $project->name }}
                    </td>
                    <td>
                        @if($project->client)
                            <div>{{ $project->client->name }}</div>
                            <div class="text-muted small">{{ $project->client->code }}</div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-muted small">
                        {{ optional($project->created_at)->format('d M Y') }}
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('projects.show', $project) }}"
                               class="btn btn-outline-secondary"
                               title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('project.project.update')
                                <a href="{{ route('projects.edit', $project) }}"
                                   class="btn btn-outline-secondary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-4">
                        <div class="erp-empty-state">
                            <div class="erp-empty-state-icon">
                                <i class="bi bi-kanban"></i>
                            </div>
                            <div class="fw-semibold text-body mb-1">No projects found</div>
                            <div class="small mb-3">Try adjusting your search or create a new project.</div>
                        @can('project.project.create')
                            <div>
                                <a href="{{ route('projects.create') }}" class="btn btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Create your first project
                                </a>
                            </div>
                        @endcan
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if($projects instanceof \Illuminate\Contracts\Pagination\Paginator && $projects->hasPages())
        <div class="mt-3">
            {{ $projects->links() }}
        </div>
    @endif
@endsection
