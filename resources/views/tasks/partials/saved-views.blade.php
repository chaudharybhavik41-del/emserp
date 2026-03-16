@php
    $currentViewType = $currentViewType ?? match (true) {
        request()->routeIs('task-board.*') => 'board',
        request()->routeIs('tasks.calendar') => 'calendar',
        request()->routeIs('tasks.timeline') => 'gantt',
        default => 'list',
    };

    $saveableKeys = [
        'q', 'list', 'project', 'bom', 'status', 'priority', 'assignee',
        'type', 'labels', 'overdue', 'due_today', 'due_this_week',
        'include_subtasks', 'sort', 'dir',
    ];
@endphp

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="small text-muted">Saved Views</span>
                @forelse($savedViews ?? [] as $savedView)
                    <a href="{{ route($savedView->targetRouteName(), ['saved_view' => $savedView->id]) }}"
                       class="btn btn-sm {{ ($selectedSavedView->id ?? null) === $savedView->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $savedView->name }}
                    </a>
                @empty
                    <span class="text-muted small">No saved views yet.</span>
                @endforelse

                @if(isset($selectedSavedView))
                    <form action="{{ route('tasks.saved-views.destroy', $selectedSavedView) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none px-1">
                            Delete Selected
                        </button>
                    </form>
                @endif
            </div>

            <form action="{{ route('tasks.saved-views.store') }}" method="POST" class="row g-2 align-items-center">
                @csrf
                <input type="hidden" name="view_type" value="{{ $currentViewType }}">
                @foreach($saveableKeys as $key)
                    @if(request()->has($key))
                        @if(is_array(request($key)))
                            @foreach(request($key) as $value)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $value }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
                        @endif
                    @endif
                @endforeach
                <div class="col-auto">
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Save current view as..." required>
                </div>
                <div class="col-auto">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="savedViewDefault">
                        <label class="form-check-label small" for="savedViewDefault">Default</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-bookmark-plus me-1"></i> Save View
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
