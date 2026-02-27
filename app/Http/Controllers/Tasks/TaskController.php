<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.view')->only(['index', 'show', 'watch', 'unwatch']);
        $this->middleware('permission:tasks.create')->only(['create', 'store', 'duplicate']);
        $this->middleware('permission:tasks.update')->only(['edit', 'update', 'updateStatus', 'updateAssignee', 'bulkUpdate']);
        $this->middleware('permission:tasks.delete')->only(['destroy', 'bulkDelete']);
    }

    protected function getCompanyId(): int
    {
        return auth()->user()->company_id ?? 1;
    }

    public function index(Request $request): View
    {
        $companyId = $this->getCompanyId();

        $query = Task::with(['status', 'priority', 'assignee', 'taskList', 'labels', 'project'])
            ->withCount([
                'children as subtask_count',
                'children as completed_subtask_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', true)),
            ])
            ->forCompany($companyId)
            ->notArchived();

        if ($taskListId = $request->get('list')) {
            $query->forList($taskListId);
        }

        if ($projectId = $request->get('project')) {
            $query->forProject($projectId);
        }

        if ($bomId = $request->get('bom')) {
            $query->where('bom_id', $bomId);
        }

        if ($statusIds = $request->get('status')) {
            $query->withStatus(explode(',', $statusIds));
        }

        if ($priorityIds = $request->get('priority')) {
            $query->withPriority(explode(',', $priorityIds));
        }

        if ($assigneeId = $request->get('assignee')) {
            if ($assigneeId === 'me') {
                $query->assignedTo(auth()->id());
            } elseif ($assigneeId === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->assignedTo($assigneeId);
            }
        }

        if ($taskType = $request->get('type')) {
            $query->ofType($taskType);
        }

        if ($search = $request->get('q')) {
            $query->search($search);
        }

        if ($request->get('overdue')) {
            $query->overdue();
        } elseif ($request->get('due_today')) {
            $query->dueToday();
        } elseif ($request->get('due_this_week')) {
            $query->dueThisWeek();
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSortFields = ['created_at', 'updated_at', 'due_date', 'title', 'task_number', 'status_id', 'priority_id'];
        if (!in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'created_at';
        }
        $query->orderBy($sortField, $sortDir);

        if (!$request->get('include_subtasks')) {
            $query->rootTasks();
        }

        $statsQuery = clone $query;
        $tasks = $query->paginate(25)->withQueryString();

        $taskLists = TaskList::active()->notArchived()->orderBy('name')->get();
        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $labels = TaskLabel::active()->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $selectedProjectId = (int) ($request->get('project') ?: 0);
        if ($selectedProjectId <= 0 && $request->filled('bom')) {
            $selectedProjectId = (int) (Bom::where('id', (int) $request->get('bom'))->value('project_id') ?: 0);
        }
        $boms = $selectedProjectId > 0
            ? Bom::query()->where('project_id', $selectedProjectId)->orderBy('bom_number')->get()
            : collect();
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'open' => (clone $statsQuery)->open()->count(),
            'completed' => (clone $statsQuery)->closed()->count(),
            'overdue' => (clone $statsQuery)->overdue()->count(),
            'due_today' => (clone $statsQuery)->dueToday()->count(),
        ];

        return view('tasks.index', compact(
            'tasks', 'taskLists', 'statuses', 'priorities', 'labels', 'users', 'projects', 'boms', 'stats'
        ));
    }

    public function create(Request $request): View
    {
        $task = new Task();
        $task->task_type = $request->get('type', 'general');

        if ($listId = $request->get('list')) {
            $task->task_list_id = $listId;
            $taskList = TaskList::find($listId);
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
            $parent = Task::find($parentId);
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
            $task->status_id = TaskStatus::where('is_default', true)->value('id');
        }

        $taskLists = TaskList::active()->notArchived()->orderBy('name')->get();
        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $labels = TaskLabel::active()->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $boms = $task->project_id
            ? Bom::query()->where('project_id', $task->project_id)->orderBy('bom_number')->get()
            : collect();
        $templates = TaskTemplate::active()->orderBy('name')->get();

        return view('tasks.create', compact(
            'task', 'taskLists', 'statuses', 'priorities', 'labels', 'users', 'projects', 'boms', 'templates'
        ));
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            if ($templateId = $request->get('template_id')) {
                $template = TaskTemplate::findOrFail($templateId);
                $taskList = TaskList::findOrFail($data['task_list_id']);
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
        $task->load([
            'status', 'priority', 'assignee', 'reporter', 'taskList', 'project', 'bom', 'labels',
            'watchers', 'parent', 'children.status', 'children.assignee', 'checklists.items',
            'comments' => fn($q) => $q->with(['user', 'replies.user'])->whereNull('parent_id'),
            'attachments', 'activities' => fn($q) => $q->with('user')->latest()->take(20),
            'dependencies.status', 'dependents.status',
            'timeEntries' => fn($q) => $q->with('user')->latest()->take(10),
        ]);

        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $labels = TaskLabel::active()->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('tasks.show', compact('task', 'statuses', 'priorities', 'labels', 'users'));
    }

    public function edit(Task $task): View
    {
        $taskLists = TaskList::active()->notArchived()->orderBy('name')->get();
        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $labels = TaskLabel::active()->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $boms = $task->project_id ? Bom::where('project_id', $task->project_id)->get() : collect();

        return view('tasks.create', compact(
            'task', 'taskLists', 'statuses', 'priorities', 'labels', 'users', 'projects', 'boms'
        ));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            $task->update($data);

            if ($request->has('labels')) {
                $task->labels()->sync($request->input('labels', []));
            }

            DB::commit();

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
        $request->validate(['status_id' => 'required|exists:task_statuses,id']);
        $task->update(['status_id' => $request->status_id]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'task' => $task->fresh(['status']),
        ]);
    }

    public function updateAssignee(Request $request, Task $task): JsonResponse
    {
        $request->validate(['assignee_id' => 'nullable|exists:users,id']);
        $task->update(['assignee_id' => $request->assignee_id]);

        if ($task->assignee_id) {
            $task->addWatcher($task->assignee);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignee updated',
            'task' => $task->fresh(['assignee']),
        ]);
    }

    public function duplicate(Task $task): RedirectResponse
    {
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
        $task->update(['is_archived' => true]);
        $task->logActivity('archived');
        return back()->with('success', 'Task archived.');
    }

    public function unarchive(Task $task): RedirectResponse
    {
        $task->update(['is_archived' => false]);
        $task->logActivity('unarchived');
        return back()->with('success', 'Task restored from archive.');
    }

    public function destroy(Task $task): RedirectResponse
    {
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

        $tasks = Task::whereIn('id', $request->task_ids)->get();
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
        $task->watchers()->syncWithoutDetaching([auth()->id()]);

        return back()->with('success', 'You are now watching this task.');
    }

    /**
     * Unwatch a task
     */
    public function unwatch(Task $task): RedirectResponse
    {
        $task->watchers()->detach(auth()->id());

        return back()->with('success', 'You are no longer watching this task.');
    }
}
