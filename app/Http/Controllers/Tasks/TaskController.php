<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Bom;
use App\Models\Project;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskLabel;
use App\Models\Tasks\TaskList;
use App\Models\Tasks\TaskPriority;
use App\Models\Tasks\TaskStatus;
use App\Models\Tasks\TaskTemplate;
use App\Models\User;
use App\Services\Tasks\TaskAutomationService;
use App\Services\Tasks\TaskNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct(
        protected TaskNotificationService $taskNotificationService,
        protected TaskAutomationService $taskAutomationService,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:tasks.view')->only(['index', 'show', 'watch', 'unwatch']);
        $this->middleware('permission:tasks.create')->only(['create', 'store', 'duplicate']);
        $this->middleware('permission:tasks.update')->only(['edit', 'update', 'updateStatus', 'updateAssignee', 'updateSchedule']);
        $this->middleware('permission:tasks.bulk_update|tasks.update')->only(['bulkUpdate']);
        $this->middleware('permission:tasks.delete')->only(['destroy', 'bulkDelete']);
    }

    public function index(Request $request): View
    {
        $selectedSavedView = $this->resolveSavedView($request);

        $query = Task::with(['status', 'priority', 'assignee', 'taskList', 'labels', 'project'])
            ->withCount([
                'children as subtask_count',
                'children as completed_subtask_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', true)),
            ])
            ->forCompany($this->getCompanyId())
            ->visibleToUser($this->taskUser())
            ->notArchived();

        $this->applyTaskFilters($query, $request);

        $sortField = $request->get('sort', 'created_at');
        $sortDir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSortFields = ['created_at', 'updated_at', 'due_date', 'title', 'task_number', 'status_id', 'priority_id'];
        if (!in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'created_at';
        }
        $query->orderBy($sortField, $sortDir);

        $statsQuery = clone $query;
        $tasks = $query->paginate(25)->withQueryString();

        $filterOptions = $this->taskFilterOptions($request);
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'open' => (clone $statsQuery)->open()->count(),
            'completed' => (clone $statsQuery)->closed()->count(),
            'overdue' => (clone $statsQuery)->overdue()->count(),
            'due_today' => (clone $statsQuery)->dueToday()->count(),
        ];

        return view('tasks.index', array_merge($filterOptions, compact(
            'tasks', 'stats', 'selectedSavedView'
        )));
    }

    public function create(Request $request): View
    {
        $task = new Task();
        $task->task_type = $request->get('type', 'general');

        if ($listId = $request->get('list')) {
            $task->task_list_id = $listId;
            $taskList = $this->visibleTaskListsQuery()->find($listId);
            if ($taskList) {
                $task->project_id = $taskList->project_id;
                $task->status_id = $taskList->default_status_id;
                $task->priority_id = $taskList->default_priority_id;
                $task->assignee_id = $taskList->default_assignee_id;
            }
        }

        if ($projectId = $request->get('project')) {
            $task->project_id = $projectId;
        }

        if ($bomId = $request->get('bom')) {
            $bom = Bom::query()->find($bomId);
            if ($bom) {
                $task->bom_id = $bom->id;
                $task->project_id = $task->project_id ?: $bom->project_id;
            }
        }

        if ($parentId = $request->get('parent')) {
            $task->parent_id = $parentId;
            $parent = $this->visibleTasksQuery()->find($parentId);
            if ($parent) {
                $task->task_list_id = $parent->task_list_id;
                $task->project_id = $parent->project_id;
                $task->bom_id = $parent->bom_id;
            }
        }

        if ($prefillTitle = trim((string) $request->get('title', ''))) {
            $task->title = $prefillTitle;
        }

        if ($prefillDescription = trim((string) $request->get('description', ''))) {
            $task->description = $prefillDescription;
        }

        if (!$task->status_id) {
            $task->status_id = TaskStatus::query()
                ->forCompany($this->getCompanyId())
                ->where('is_default', true)
                ->value('id');
        }

        $requestForOptions = Request::create('', 'GET', [
            'project' => $task->project_id,
            'bom' => $task->bom_id,
        ]);
        $filterOptions = $this->taskFilterOptions($requestForOptions);

        return view('tasks.create', array_merge($filterOptions, compact('task')));
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $taskList = $this->visibleTaskListsQuery()->findOrFail((int) $data['task_list_id']);
        $this->authorizeTaskListEdit($taskList);
        $data['company_id'] = $taskList->company_id;

        DB::beginTransaction();
        try {
            if ($templateId = $request->get('template_id')) {
                $template = TaskTemplate::query()
                    ->forCompany($this->getCompanyId())
                    ->findOrFail($templateId);
                $task = $template->createTask($taskList, $data);
            } else {
                $task = Task::create($data);
            }

            if ($request->has('labels')) {
                $task->labels()->sync($request->input('labels', []));
            }

            $task->addWatcher(auth()->user());

            if ($task->assignee_id && $task->assignee_id !== auth()->id()) {
                $task->addWatcher($task->assignee);
            }

            DB::commit();

            $this->taskNotificationService->notifyAssignment($task, $this->taskUser());
            $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'created');

            if ($request->get('create_another')) {
                return redirect()
                    ->route('tasks.create', ['list' => $task->task_list_id])
                    ->with('success', "Task {$task->task_number} created successfully.");
            }

            return redirect()
                ->route('tasks.show', $task)
                ->with('success', "Task {$task->task_number} created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error creating task: ' . $e->getMessage());
        }
    }

    public function show(Task $task): View
    {
        $this->authorizeTaskView($task);

        $task->load([
            'status', 'priority', 'assignee', 'reporter', 'taskList', 'project', 'bom', 'labels',
            'watchers', 'parent', 'children.status', 'children.assignee', 'checklists.items',
            'comments' => fn($q) => $q->with(['user', 'replies.user', 'attachments'])->whereNull('parent_id'),
            'attachments', 'activities' => fn($q) => $q->with('user')->latest()->take(20),
            'dependencies.status', 'dependents.status',
            'timeEntries' => fn($q) => $q->with('user')->latest()->take(10),
        ]);

        $dependencyOptions = $this->visibleTasksQuery(['status'])
            ->where('id', '!=', $task->id)
            ->when($task->task_list_id, fn ($query) => $query->where('task_list_id', $task->task_list_id))
            ->orderBy('title')
            ->limit(100)
            ->get();
        $runningTimeEntry = $task->timeEntries()
            ->where('user_id', $this->taskUser()->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
        $filterOptions = $this->taskFilterOptions(new Request([
            'project' => $task->project_id,
            'bom' => $task->bom_id,
        ]));

        return view('tasks.show', array_merge($filterOptions, compact(
            'task', 'dependencyOptions', 'runningTimeEntry'
        )));
    }

    public function edit(Task $task): View
    {
        $this->authorizeTaskEdit($task);

        $filterOptions = $this->taskFilterOptions(new Request([
            'project' => $task->project_id,
            'bom' => $task->bom_id,
        ]));

        return view('tasks.create', array_merge($filterOptions, compact('task')));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskEdit($task);
        $data = $request->validated();
        $originalAssigneeId = $task->assignee_id;
        $changedFields = array_keys($data);

        if (!empty($data['task_list_id'])) {
            $taskList = $this->visibleTaskListsQuery()->findOrFail((int) $data['task_list_id']);
            $this->authorizeTaskListEdit($taskList);
            $data['company_id'] = $taskList->company_id;
        }

        DB::beginTransaction();
        try {
            $task->update($data);

            if ($request->has('labels')) {
                $task->labels()->sync($request->input('labels', []));
            }

            DB::commit();

            if ((int) $originalAssigneeId !== (int) $task->assignee_id) {
                $this->taskNotificationService->notifyAssignment($task->fresh(['assignee', 'taskList', 'project']), $this->taskUser());
            }
            $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'updated', [
                'changed_fields' => $changedFields,
            ]);

            return redirect()
                ->route('tasks.show', $task)
                ->with('success', 'Task updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error updating task: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTaskEdit($task);
        $request->validate(['status_id' => 'required|exists:task_statuses,id']);
        $task->update(['status_id' => $request->status_id]);
        $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'status_changed', [
            'changed_fields' => ['status_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'task' => $task->fresh(['status']),
        ]);
    }

    public function updateSchedule(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorizeTaskEdit($task);

        $data = $request->validate([
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        foreach (['start_date', 'due_date'] as $field) {
            $data[$field] = filled($data[$field] ?? null)
                ? Carbon::parse($data[$field])->toDateString()
                : null;
        }

        $task->update($data);
        $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'updated', [
            'changed_fields' => ['start_date', 'due_date'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task schedule updated.',
                'task' => $task->fresh(),
            ]);
        }

        return back()->with('success', 'Task schedule updated.');
    }

    public function updateAssignee(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTaskEdit($task);
        $request->validate(['assignee_id' => 'nullable|exists:users,id']);
        $originalAssigneeId = $task->assignee_id;
        $task->update(['assignee_id' => $request->assignee_id]);

        if ($task->assignee_id) {
            $task->addWatcher($task->assignee);
        }

        if ((int) $originalAssigneeId !== (int) $task->assignee_id) {
            $this->taskNotificationService->notifyAssignment($task->fresh(['assignee', 'taskList', 'project']), $this->taskUser());
        }
        $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'updated', [
            'changed_fields' => ['assignee_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assignee updated',
            'task' => $task->fresh(['assignee']),
        ]);
    }

    public function duplicate(Task $task): RedirectResponse
    {
        $this->authorizeTaskEdit($task);

        try {
            $newTask = $task->duplicateTask();
            return redirect()
                ->route('tasks.edit', $newTask)
                ->with('success', "Task duplicated as {$newTask->task_number}");
        } catch (\Exception $e) {
            return back()->with('error', 'Error duplicating task: ' . $e->getMessage());
        }
    }

    public function archive(Task $task): RedirectResponse
    {
        $this->authorizeTaskEdit($task);
        $task->update(['is_archived' => true]);
        $task->logActivity('archived');
        return back()->with('success', 'Task archived.');
    }

    public function unarchive(Task $task): RedirectResponse
    {
        $this->authorizeTaskEdit($task);
        $task->update(['is_archived' => false]);
        $task->logActivity('unarchived');
        return back()->with('success', 'Task restored from archive.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorizeTaskEdit($task);
        $taskNumber = $task->task_number;
        $listId = $task->task_list_id;
        $task->delete();

        return redirect()
            ->route('tasks.index', ['list' => $listId])
            ->with('success', "Task {$taskNumber} deleted.");
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
            'action' => 'required|in:status,priority,assignee,archive,delete',
            'value' => 'nullable',
        ]);

        if ($request->action === 'delete' && !$this->taskUser()->can('tasks.delete')) {
            return response()->json(['success' => false, 'message' => 'Delete permission is required.'], 403);
        }

        $tasks = $this->visibleTasksQuery()
            ->whereIn('id', $request->task_ids)
            ->get()
            ->filter(fn (Task $task) => $task->canEdit($this->taskUser(), $this->getCompanyId()));
        $count = $tasks->count();

        DB::beginTransaction();
        try {
            foreach ($tasks as $task) {
                match($request->action) {
                    'status' => $task->update(['status_id' => $request->value]),
                    'priority' => $task->update(['priority_id' => $request->value]),
                    'assignee' => $task->update(['assignee_id' => $request->value]),
                    'archive' => $task->update(['is_archived' => true]),
                    'delete' => $task->delete(),
                };
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => "{$count} tasks updated."]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function myTasks(Request $request): View
    {
        $request->merge(['assignee' => 'me']);
        return $this->index($request);
    }

    /**
     * Watch a task
     */
    public function watch(Task $task): RedirectResponse
    {
        $this->authorizeTaskView($task);
        $task->watchers()->syncWithoutDetaching([auth()->id()]);

        return back()->with('success', 'You are now watching this task.');
    }

    /**
     * Unwatch a task
     */
    public function unwatch(Task $task): RedirectResponse
    {
        $this->authorizeTaskView($task);
        $task->watchers()->detach(auth()->id());

        return back()->with('success', 'You are no longer watching this task.');
    }
}
