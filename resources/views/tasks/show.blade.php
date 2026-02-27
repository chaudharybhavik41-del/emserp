@extends('layouts.erp')

@section('title', $task->task_number . ' - ' . $task->title)

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Tasks</a></li>
                    @if($task->taskList)
                    <li class="breadcrumb-item"><a href="{{ route('task-lists.show', $task->taskList) }}">{{ $task->taskList->name }}</a></li>
                    @endif
                    <li class="breadcrumb-item active">{{ $task->task_number }}</li>
                </ol>
            </nav>
            <h1 class="h4 mb-0">{{ $task->title }}</h1>
            @if($task->parent)
            <small class="text-muted">
                Subtask of <a href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->task_number }}</a>
            </small>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <a href="{{ route('tasks.edit', $task) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('tasks.duplicate', $task) }}" onclick="event.preventDefault(); document.getElementById('duplicate-form').submit();">
                        <i class="bi bi-copy me-2"></i> Duplicate
                    </a></li>
                    <li><a class="dropdown-item" href="{{ route('tasks.create', ['parent' => $task->id]) }}">
                        <i class="bi bi-plus me-2"></i> Add Subtask
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    @if($task->is_archived)
                    <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('unarchive-form').submit();">
                        <i class="bi bi-archive me-2"></i> Unarchive
                    </a></li>
                    @else
                    <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('archive-form').submit();">
                        <i class="bi bi-archive me-2"></i> Archive
                    </a></li>
                    @endif
                    <li><a class="dropdown-item text-danger" href="#" onclick="if(confirm('Delete this task?')) document.getElementById('delete-form').submit();">
                        <i class="bi bi-trash me-2"></i> Delete
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <form id="duplicate-form" action="{{ route('tasks.duplicate', $task) }}" method="POST" class="d-none">@csrf</form>
    <form id="archive-form" action="{{ route('tasks.archive', $task) }}" method="POST" class="d-none">@csrf</form>
    <form id="unarchive-form" action="{{ route('tasks.unarchive', $task) }}" method="POST" class="d-none">@csrf</form>
    <form id="delete-form" action="{{ route('tasks.destroy', $task) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>

    <div class="row">
        {{-- Main Content --}}
        <div class="col-lg-8">
            {{-- Status Bar --}}
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <label class="small text-muted me-2">Status:</label>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                        style="background-color: {{ $task->status->color }}; color: white;">
                                    {{ $task->status->name }}
                                </button>
                                <ul class="dropdown-menu">
                                    @foreach($statuses as $status)
                                    <li>
                                        <a class="dropdown-item {{ $task->status_id == $status->id ? 'active' : '' }}" 
                                           href="#" onclick="updateStatus({{ $status->id }})">
                                            <span class="badge me-2" style="background-color: {{ $status->color }}">
                                                {{ $status->name }}
                                            </span>
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="col-auto">
                            <label class="small text-muted me-2">Priority:</label>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    @if($task->priority)
                                    <span style="color: {{ $task->priority->color }}">
                                        <i class="{{ $task->priority->icon }} me-1"></i>{{ $task->priority->name }}
                                    </span>
                                    @else
                                    No Priority
                                    @endif
                                </button>
                                <ul class="dropdown-menu">
                                    @foreach($priorities as $priority)
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="updatePriority({{ $priority->id }})">
                                            <i class="{{ $priority->icon }} me-2" style="color: {{ $priority->color }}"></i>
                                            {{ $priority->name }}
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="col-auto">
                            <label class="small text-muted me-2">Progress:</label>
                            <div class="d-inline-flex align-items-center">
                                <div class="progress me-2" style="width: 100px; height: 8px;">
                                    <div class="progress-bar {{ $task->progress_percent == 100 ? 'bg-success' : 'bg-primary' }}" 
                                         style="width: {{ $task->progress_percent }}%"></div>
                                </div>
                                <span class="small">{{ $task->progress_percent }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Description</strong>
                </div>
                <div class="card-body">
                    @if($task->description)
                    <div class="task-description">{!! nl2br(e($task->description)) !!}</div>
                    @else
                    <p class="text-muted mb-0">No description provided.</p>
                    @endif
                </div>
            </div>

            {{-- Subtasks --}}
            @if($task->children->count() > 0 || !$task->parent_id)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="bi bi-list-task me-1"></i> Subtasks
                        <span class="badge bg-secondary ms-1">{{ $task->completed_subtask_count }}/{{ $task->subtask_count }}</span>
                    </strong>
                    <a href="{{ route('tasks.create', ['parent' => $task->id, 'list' => $task->task_list_id]) }}" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus"></i> Add Subtask
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($task->children->count() > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($task->children as $subtask)
                        <li class="list-group-item d-flex align-items-center">
                            <span class="badge me-2" style="background-color: {{ $subtask->status->color }}; width: 10px; height: 10px; padding: 0;"></span>
                            <a href="{{ route('tasks.show', $subtask) }}" class="text-decoration-none flex-grow-1 {{ $subtask->isClosed() ? 'text-muted text-decoration-line-through' : '' }}">
                                {{ $subtask->title }}
                            </a>
                            @if($subtask->assignee)
                            <span class="badge bg-light text-dark">{{ $subtask->assignee->name }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-muted text-center py-3 mb-0">No subtasks yet.</p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Checklists --}}
            @if($task->checklists->count() > 0)
            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="bi bi-check2-square me-1"></i> Checklists</strong>
                </div>
                <div class="card-body">
                    @foreach($task->checklists as $checklist)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>{{ $checklist->title }}</strong>
                            <span class="small text-muted">{{ $checklist->completed_items }}/{{ $checklist->total_items }}</span>
                        </div>
                        <div class="progress mb-2" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: {{ $checklist->progress_percent }}%"></div>
                        </div>
                        @foreach($checklist->items as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   id="item-{{ $item->id }}" 
                                   {{ $item->is_completed ? 'checked' : '' }}
                                   onchange="toggleChecklistItem(this, {{ $task->id }}, {{ $checklist->id }}, {{ $item->id }})">
                            <label class="form-check-label {{ $item->is_completed ? 'text-muted text-decoration-line-through' : '' }}" 
                                   for="item-{{ $item->id }}">
                                {{ $item->content }}
                            </label>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Comments --}}
            <div class="card mb-3">
                <div class="card-header">
                    <strong><i class="bi bi-chat-dots me-1"></i> Comments</strong>
                    <span class="badge bg-secondary ms-1">{{ $task->comments->count() }}</span>
                </div>
                <div class="card-body">
                    {{-- Add Comment Form --}}
                    <form action="{{ route('tasks.comments.store', $task) }}" method="POST" class="mb-3">
                        @csrf
                        <div class="mb-2">
                            <textarea name="content" class="form-control" rows="3" placeholder="Add a comment..."></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_internal" id="isInternal" value="1">
                                <label class="form-check-label small" for="isInternal">Internal note</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-send me-1"></i> Comment
                            </button>
                        </div>
                    </form>

                    {{-- Comments List --}}
                    @forelse($task->comments as $comment)
                    <div class="d-flex mb-3 {{ $comment->is_internal ? 'bg-warning-subtle p-2 rounded' : '' }}">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-sm">
                                <span class="avatar-initial rounded-circle bg-primary">
                                    {{ substr($comment->user->name ?? 'U', 0, 1) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $comment->user->name ?? 'Unknown' }}</strong>
                                    @if($comment->is_internal)
                                    <span class="badge bg-warning text-dark ms-1">Internal</span>
                                    @endif
                                    <span class="small text-muted ms-2">{{ $comment->created_at->diffForHumans() }}</span>
                                    @if($comment->isEdited())
                                    <span class="small text-muted">(edited)</span>
                                    @endif
                                </div>
                                @if($comment->canEdit(auth()->user()))
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="editComment({{ $task->id }}, {{ $comment->id }}, @js($comment->content))">Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteComment({{ $task->id }}, {{ $comment->id }})">Delete</a></li>
                                    </ul>
                                </div>
                                @endif
                            </div>
                            <div class="mt-1">{!! $comment->formatted_content !!}</div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center mb-0">No comments yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Activity Log --}}
            <div class="card">
                <div class="card-header">
                    <strong><i class="bi bi-clock-history me-1"></i> Activity</strong>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($task->activities as $activity)
                        <li class="d-flex align-items-start mb-2">
                            <i class="{{ $activity->icon }} me-2 mt-1"></i>
                            <div>
                                <span>{{ $activity->description }}</span>
                                <small class="text-muted d-block">{{ $activity->created_at->diffForHumans() }}</small>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Details Card --}}
            <div class="card mb-3">
                <div class="card-header"><strong>Details</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Task Number</dt>
                        <dd class="col-7">{{ $task->task_number }}</dd>

                        <dt class="col-5 text-muted">Task Type</dt>
                        <dd class="col-7">
                            <span class="badge" style="background-color: {{ $task->task_type_color }}">
                                {{ $task->task_type_name }}
                            </span>
                        </dd>

                        <dt class="col-5 text-muted">Assignee</dt>
                        <dd class="col-7">
                            <div class="dropdown">
                                <a href="#" class="text-decoration-none" data-bs-toggle="dropdown">
                                    @if($task->assignee)
                                    {{ $task->assignee->name }}
                                    @else
                                    <span class="text-muted">Unassigned</span>
                                    @endif
                                    <i class="bi bi-chevron-down ms-1 small"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="updateAssignee(null)">Unassigned</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($users as $user)
                                    <li><a class="dropdown-item {{ $task->assignee_id == $user->id ? 'active' : '' }}" 
                                           href="#" onclick="updateAssignee({{ $user->id }})">{{ $user->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </dd>

                        <dt class="col-5 text-muted">Reporter</dt>
                        <dd class="col-7">{{ $task->reporter->name ?? '-' }}</dd>

                        <dt class="col-5 text-muted">Task List</dt>
                        <dd class="col-7">
                            @if($task->taskList)
                            <a href="{{ route('task-lists.show', $task->taskList) }}">{{ $task->taskList->name }}</a>
                            @else
                            -
                            @endif
                        </dd>

                        <dt class="col-5 text-muted">Project</dt>
                        <dd class="col-7">
                            @if($task->project)
                            <a href="{{ route('projects.show', $task->project) }}">{{ $task->project->code }}</a>
                            @else
                            -
                            @endif
                        </dd>

                        @if($task->bom)
                        <dt class="col-5 text-muted">BOM</dt>
                        <dd class="col-7">{{ $task->bom->bom_number }}</dd>
                        @endif

                        <dt class="col-5 text-muted">Start Date</dt>
                        <dd class="col-7">{{ $task->start_date?->format('d M Y') ?? '-' }}</dd>

                        <dt class="col-5 text-muted">Due Date</dt>
                        <dd class="col-7 {{ $task->isOverdue() ? 'text-danger fw-bold' : '' }}">
                            {{ $task->due_date?->format('d M Y') ?? '-' }}
                            @if($task->isOverdue())
                            <span class="badge bg-danger ms-1">Overdue</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted">Estimated</dt>
                        <dd class="col-7">{{ $task->estimated_hours ? $task->estimated_hours . 'h' : '-' }}</dd>

                        <dt class="col-5 text-muted">Time Logged</dt>
                        <dd class="col-7">{{ $task->logged_hours }}h</dd>

                        <dt class="col-5 text-muted">Created</dt>
                        <dd class="col-7">{{ $task->created_at->format('d M Y H:i') }}</dd>

                        @if($task->completed_at)
                        <dt class="col-5 text-muted">Completed</dt>
                        <dd class="col-7">{{ $task->completed_at->format('d M Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Labels --}}
            <div class="card mb-3">
                <div class="card-header"><strong>Labels</strong></div>
                <div class="card-body">
                    @if($task->labels->count() > 0)
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($task->labels as $label)
                        <span class="badge" style="background-color: {{ $label->color }}; color: {{ $label->text_color }}">
                            {{ $label->name }}
                        </span>
                        @endforeach
                    </div>
                    @else
                    <span class="text-muted small">No labels</span>
                    @endif
                </div>
            </div>

            {{-- Watchers --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Watchers</strong>
                    @if(Route::has('tasks.watch'))
                        @if($task->isWatching(auth()->user()))
                        <a href="#" onclick="event.preventDefault(); document.getElementById('unwatch-form').submit();" class="small">
                            <i class="bi bi-eye-slash"></i> Unwatch
                        </a>
                        @else
                        <a href="#" onclick="event.preventDefault(); document.getElementById('watch-form').submit();" class="small">
                            <i class="bi bi-eye"></i> Watch
                        </a>
                        @endif
                    @endif
                </div>
                <div class="card-body">
                    @if($task->watchers->count() > 0)
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($task->watchers as $watcher)
                        <span class="badge bg-light text-dark">{{ $watcher->name }}</span>
                        @endforeach
                    </div>
                    @else
                    <span class="text-muted small">No watchers</span>
                    @endif
                </div>
            </div>
            @if(Route::has('tasks.watch'))
            <form id="watch-form" action="{{ route('tasks.watch', $task) }}" method="POST" class="d-none">@csrf</form>
            <form id="unwatch-form" action="{{ route('tasks.unwatch', $task) }}" method="POST" class="d-none">@csrf</form>
            @endif

            {{-- Dependencies --}}
            @if($task->dependencies->count() > 0 || $task->dependents->count() > 0)
            <div class="card mb-3">
                <div class="card-header"><strong>Dependencies</strong></div>
                <div class="card-body">
                    @if($task->dependencies->count() > 0)
                    <div class="mb-2">
                        <small class="text-muted">Blocked by:</small>
                        @foreach($task->dependencies as $dep)
                        <div class="d-flex align-items-center">
                            <span class="badge me-2" style="background-color: {{ $dep->status->color }}; width: 8px; height: 8px; padding: 0;"></span>
                            <a href="{{ route('tasks.show', $dep) }}" class="small">{{ $dep->task_number }}</a>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @if($task->dependents->count() > 0)
                    <div>
                        <small class="text-muted">Blocking:</small>
                        @foreach($task->dependents as $dep)
                        <div class="d-flex align-items-center">
                            <span class="badge me-2" style="background-color: {{ $dep->status->color }}; width: 8px; height: 8px; padding: 0;"></span>
                            <a href="{{ route('tasks.show', $dep) }}" class="small">{{ $dep->task_number }}</a>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Time Entries --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Time Tracking</strong>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#logTimeModal">
                        <i class="bi bi-plus"></i> Log Time
                    </button>
                </div>
                <div class="card-body">
                    @if($task->timeEntries->count() > 0)
                    <ul class="list-unstyled mb-0 small">
                        @foreach($task->timeEntries as $entry)
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>{{ $entry->duration_formatted }}</strong>
                                <span class="text-muted">by {{ $entry->user->name }}</span>
                                @if($entry->description)
                                <div class="small text-muted">{{ Str::limit($entry->description, 50) }}</div>
                                @endif
                            </div>
                            <small class="text-muted">{{ $entry->started_at->diffForHumans() }}</small>
                        </li>
                        @endforeach
                    </ul>
                    <div class="border-top pt-2 mt-2">
                        <strong>Total: {{ $task->logged_hours }}h</strong>
                        @if($task->estimated_minutes)
                        <span class="text-muted">/ {{ $task->estimated_hours }}h estimated</span>
                        @endif
                    </div>
                    @else
                    <p class="text-muted small mb-0">No time logged yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Log Time Modal --}}
<div class="modal fade" id="logTimeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('tasks.time-entries.store', $task) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Log Time</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Hours</label>
                            <input type="number" name="hours" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Minutes</label>
                            <input type="number" name="minutes" class="form-control" min="0" max="59" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log Time</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function updateStatus(statusId) {
    fetch('{{ route("tasks.update-status", $task) }}', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ status_id: statusId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Unable to update status.');
        }
        location.reload();
    })
    .catch(error => alert(error.message));
}

function updateAssignee(userId) {
    fetch('{{ route("tasks.update-assignee", $task) }}', {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ assignee_id: userId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Unable to update assignee.');
        }
        location.reload();
    })
    .catch(error => alert(error.message));
}

function updatePriority(priorityId) {
    fetch('{{ route("tasks.update", $task) }}', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ priority_id: priorityId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Unable to update priority.');
        }
        location.reload();
    })
    .catch(error => alert(error.message));
}

function editComment(taskId, commentId, currentContent) {
    const content = prompt('Edit comment', currentContent ?? '');
    if (content === null) {
        return;
    }

    const trimmed = content.trim();
    if (!trimmed) {
        alert('Comment cannot be empty.');
        return;
    }

    fetch(`/tasks/${taskId}/comments/${commentId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ content: trimmed })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Unable to edit comment.');
        }
        location.reload();
    })
    .catch(error => alert(error.message));
}

function deleteComment(taskId, commentId) {
    if (confirm('Delete this comment?')) {
        fetch(`/tasks/${taskId}/comments/${commentId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to delete comment.');
            }
            location.reload();
        })
        .catch(error => alert(error.message));
    }
}

function toggleChecklistItem(checkbox, taskId, checklistId, itemId) {
    fetch(`/tasks/${taskId}/checklists/${checklistId}/items/${itemId}/toggle`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => {
        if (!response.ok) {
            checkbox.checked = !checkbox.checked;
            throw new Error('Unable to update checklist item.');
        }
    })
    .catch(error => alert(error.message));
}
</script>
@endpush

@push('styles')
<style>
.avatar-sm { width: 32px; height: 32px; }
.avatar-initial { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; font-size: 14px; color: white; }
</style>
@endpush
@endsection
