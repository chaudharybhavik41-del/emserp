@extends('layouts.erp')

@section('title', 'Task Statuses')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-gear me-2"></i>Task Settings
            </h1>
        </div>
    </div>

    {{-- Settings Tabs --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('task-settings.statuses.index') }}">
                <i class="bi bi-circle me-1"></i> Statuses
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.priorities.index') }}">
                <i class="bi bi-flag me-1"></i> Priorities
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.labels.index') }}">
                <i class="bi bi-tag me-1"></i> Labels
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('task-settings.templates.index') }}">
                <i class="bi bi-file-earmark-text me-1"></i> Templates
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Task Statuses</span>
            @can('tasks.settings.manage')
            <a href="{{ route('task-settings.statuses.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus me-1"></i> Add Status
            </a>
            @endcan
        </div>

        <div class="px-3 pt-3 pb-2 border-bottom">
            <form method="GET" action="{{ route('task-settings.statuses.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Search</label>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           class="form-control form-control-sm"
                           placeholder="Name">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="not_started" {{ request('category') === 'not_started' ? 'selected' : '' }}>Not started</option>
                        <option value="in_progress" {{ request('category') === 'in_progress' ? 'selected' : '' }}>In progress</option>
                        <option value="completed" {{ request('category') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ request('category') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Closed</label>
                    <select name="closed" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="open" {{ request('closed') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="closed" {{ request('closed') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Default</label>
                    <select name="default" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="default" {{ request('default') === 'default' ? 'selected' : '' }}>Default</option>
                        <option value="non_default" {{ request('default') === 'non_default' ? 'selected' : '' }}>Non-default</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Sort</label>
                    <div class="input-group input-group-sm">
                        <select name="sort" class="form-select form-select-sm">
                            <option value="">List order</option>
                            <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="tasks_count" {{ request('sort') === 'tasks_count' ? 'selected' : '' }}>Tasks</option>
                            <option value="category" {{ request('sort') === 'category' ? 'selected' : '' }}>Category</option>
                        </select>
                        <select name="dir" class="form-select form-select-sm" style="max-width: 110px;">
                            <option value="asc" {{ request('dir', 'asc') === 'asc' ? 'selected' : '' }}>Asc</option>
                            <option value="desc" {{ request('dir') === 'desc' ? 'selected' : '' }}>Desc</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                    <a href="{{ route('task-settings.statuses.index') }}" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Color</th>
                        <th class="text-center">Tasks</th>
                        <th class="text-center">Default</th>
                        <th class="text-center">Closed</th>
                        <th style="width: 120px;"></th>
                    </tr>
                </thead>
                <tbody id="statusList">
                    @forelse($statuses as $status)
                    <tr data-id="{{ $status->id }}">
                        <td class="text-center text-muted">
                            <i class="bi bi-grip-vertical handle" style="cursor: grab;"></i>
                        </td>
                        <td>
                            <span class="badge" style="background-color: {{ $status->color }};">
                                @if($status->icon)<i class="{{ $status->icon }} me-1"></i>@endif
                                {{ $status->name }}
                            </span>
                        </td>
                        <td>
                            <span class="text-capitalize">{{ str_replace('_', ' ', $status->category) }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 20px; height: 20px; background: {{ $status->color }}; border-radius: 4px;"></div>
                                <code class="small">{{ $status->color }}</code>
                            </div>
                        </td>
                        <td class="text-center">{{ $status->tasks_count }}</td>
                        <td class="text-center">
                            @if($status->is_default)
                            <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                            <i class="bi bi-circle text-muted"></i>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($status->is_closed)
                            <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                            <i class="bi bi-circle text-muted"></i>
                            @endif
                        </td>
                        <td>
                            @can('tasks.settings.manage')
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('task-settings.statuses.edit', $status) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if(!$status->is_default && $status->tasks_count == 0)
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="if(confirm('Delete this status?')) document.getElementById('delete-{{ $status->id }}').submit();">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <form id="delete-{{ $status->id }}" action="{{ route('task-settings.statuses.destroy', $status) }}" method="POST" class="d-none">
                                    @csrf @method('DELETE')
                                </form>
                                @endif
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No statuses defined yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Drag and drop to reorder statuses. The order affects how they appear in dropdowns and the task board.
        </small>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('statusList');
    if (el) {
        new Sortable(el, {
            handle: '.handle',
            animation: 150,
            onEnd: function() {
                const order = Array.from(el.querySelectorAll('tr[data-id]')).map(row => row.dataset.id);
                fetch('{{ route("task-settings.statuses.reorder") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ order: order })
                });
            }
        });
    }
});
</script>
@endpush
@endsection


