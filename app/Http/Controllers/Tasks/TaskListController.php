<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskList;
use App\Models\Tasks\TaskPriority;
use App\Models\Tasks\TaskStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskListController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.list.view')->only(['index', 'show']);
        $this->middleware('permission:tasks.list.create')->only(['create', 'store']);
        $this->middleware('permission:tasks.list.update')->only(['edit', 'update']);
        $this->middleware('permission:tasks.list.delete')->only(['destroy']);
    }

    protected function getCompanyId(): int
    {
        return auth()->user()->company_id ?? 1;
    }

    public function index(Request $request): View
    {
        $query = TaskList::with(['project', 'owner', 'defaultStatus', 'defaultPriority'])
            ->withCount([
                'tasks',
                'tasks as open_tasks_count' => function ($q) {
                    $q->whereHas('status', fn($s) => $s->where('is_closed', false));
                },
                'tasks as completed_tasks_count' => function ($q) {
                    $q->whereHas('status', fn($s) => $s->where('is_closed', true));
                },
            ])
            ->forCompany($this->getCompanyId());

        if ($projectId = $request->get('project')) {
            $query->forProject($projectId);
        }

        if ($search = $request->get('q')) {
            $query->search($search);
        }

        if ($request->boolean('archived')) {
            $query->where('is_archived', true);
        } else {
            $query->notArchived();
        }

        $taskLists = $query->rootLists()->orderBy('sort_order')->orderBy('name')->get();

        $projects = Project::where('status', 'active')->orderBy('name')->get();

        // Statistics
        $stats = [
            'total_lists' => TaskList::forCompany($this->getCompanyId())->active()->count(),
            'total_tasks' => Task::forCompany($this->getCompanyId())->notArchived()->count(),
            'open_tasks' => Task::forCompany($this->getCompanyId())->notArchived()->open()->count(),
            'overdue_tasks' => Task::forCompany($this->getCompanyId())->notArchived()->overdue()->count(),
        ];

        return view('tasks.lists.index', compact('taskLists', 'projects', 'stats'));
    }

    public function create(Request $request): View
    {
        $taskList = new TaskList();

        if ($projectId = $request->get('project')) {
            $taskList->project_id = $projectId;
        }

        if ($parentId = $request->get('parent')) {
            $taskList->parent_id = $parentId;
        }

        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $parentLists = TaskList::active()->rootLists()->orderBy('name')->get();

        return view('tasks.lists.create', compact(
            'taskList', 'projects', 'statuses', 'priorities', 'users', 'parentLists'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:task_lists,id',
            'default_status_id' => 'nullable|exists:task_statuses,id',
            'default_priority_id' => 'nullable|exists:task_priorities,id',
            'default_assignee_id' => 'nullable|exists:users,id',
            'visibility' => 'required|in:private,team,public',
        ]);

        $data['company_id'] = $this->getCompanyId();
        $data['owner_id'] = auth()->id();

        $taskList = TaskList::create($data);

        // Add creator as owner member
        $taskList->members()->syncWithoutDetaching([
            auth()->id() => ['role' => 'owner'],
        ]);

        return redirect()
            ->route('task-lists.show', $taskList)
            ->with('success', "List '{$taskList->name}' created successfully.");
    }

    public function show(TaskList $taskList, Request $request): View
    {
        $taskList->load([
            'project',
            'owner',
            'members',
            'children' => fn($q) => $q->withCount('tasks'),
        ])->loadCount([
            'tasks',
            'tasks as open_tasks_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', false)),
            'tasks as completed_tasks_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', true)),
        ]);

        // Get tasks for this list
        $query = Task::with(['status', 'priority', 'assignee', 'labels'])
            ->forList($taskList->id)
            ->notArchived()
            ->rootTasks();

        // Apply filters
        if ($statusId = $request->get('status')) {
            $query->withStatus($statusId);
        }

        if ($priorityId = $request->get('priority')) {
            $query->withPriority($priorityId);
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

        if ($search = $request->get('q')) {
            $query->search($search);
        }

        $sortField = $request->get('sort', 'position');
        $sortDir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSortFields = ['position', 'created_at', 'updated_at', 'due_date', 'title', 'task_number'];
        if (!in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'position';
        }
        $query->orderBy($sortField, $sortDir);

        $tasks = $query->paginate(25)->withQueryString();

        // Group by status for board view (get all for grouping)
        $allTasks = Task::with(['status'])
            ->forList($taskList->id)
            ->notArchived()
            ->rootTasks()
            ->get();
        $tasksByStatus = $allTasks->groupBy('status_id');

        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        $viewType = $request->get('view', 'list'); // list, board, table

        return view('tasks.lists.show', compact(
            'taskList', 'tasks', 'tasksByStatus', 'statuses', 'priorities', 'users', 'viewType'
        ));
    }

    public function edit(TaskList $taskList): View
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $parentLists = TaskList::active()
            ->rootLists()
            ->where('id', '!=', $taskList->id)
            ->orderBy('name')
            ->get();

        return view('tasks.lists.edit', compact(
            'taskList', 'projects', 'statuses', 'priorities', 'users', 'parentLists'
        ));
    }

    public function update(Request $request, TaskList $taskList): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:task_lists,id',
            'default_status_id' => 'nullable|exists:task_statuses,id',
            'default_priority_id' => 'nullable|exists:task_priorities,id',
            'default_assignee_id' => 'nullable|exists:users,id',
            'visibility' => 'required|in:private,team,public',
        ]);

        // Prevent self-referencing
        if (isset($data['parent_id']) && $data['parent_id'] == $taskList->id) {
            $data['parent_id'] = null;
        }

        $taskList->update($data);

        return redirect()
            ->route('task-lists.show', $taskList)
            ->with('success', 'List updated successfully.');
    }

    public function archive(TaskList $taskList): RedirectResponse
    {
        $taskList->update(['is_archived' => true]);

        return redirect()
            ->route('task-lists.index')
            ->with('success', "List '{$taskList->name}' archived.");
    }

    public function unarchive(TaskList $taskList): RedirectResponse
    {
        $taskList->update(['is_archived' => false]);

        return back()->with('success', "List '{$taskList->name}' restored.");
    }

    public function destroy(TaskList $taskList): RedirectResponse
    {
        if ($taskList->tasks()->count() > 0) {
            return back()->with('error', 'Cannot delete list with tasks. Archive it instead or move tasks first.');
        }

        $name = $taskList->name;
        $taskList->delete();

        return redirect()
            ->route('task-lists.index')
            ->with('success', "List '{$name}' deleted.");
    }

    /**
     * Kanban board view
     */
    public function board(TaskList $taskList): View
    {
        $taskList->load(['project', 'owner']);

        $tasks = Task::with(['status', 'priority', 'assignee', 'labels', 'children'])
            ->withCount([
                'comments',
                'children as subtask_count',
                'children as completed_subtask_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', true)),
            ])
            ->forList($taskList->id)
            ->notArchived()
            ->rootTasks()
            ->orderBy('position')
            ->get();

        $tasksByStatus = $tasks->groupBy('status_id');

        $statuses = TaskStatus::active()->ordered()->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('tasks.lists.board', compact(
            'taskList', 'tasks', 'tasksByStatus', 'statuses', 'priorities', 'users'
        ));
    }

    /**
     * Update task positions (drag & drop)
     */
    public function updatePositions(Request $request, TaskList $taskList): JsonResponse
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.position' => 'required|integer',
            'tasks.*.status_id' => 'nullable|exists:task_statuses,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->tasks as $taskData) {
                $task = Task::find($taskData['id']);
                
                if ($task->task_list_id !== $taskList->id) {
                    continue;
                }

                $updateData = ['position' => $taskData['position']];
                
                if (isset($taskData['status_id'])) {
                    $updateData['status_id'] = $taskData['status_id'];
                }

                $task->update($updateData);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Positions updated']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add member to list
     */
    public function addMember(Request $request, TaskList $taskList): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,member,viewer',
        ]);

        $taskList->members()->syncWithoutDetaching([
            $request->user_id => ['role' => $request->role]
        ]);

        return response()->json(['success' => true, 'message' => 'Member added']);
    }

    /**
     * Remove member from list
     */
    public function removeMember(TaskList $taskList, User $user): JsonResponse
    {
        if ($taskList->owner_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Cannot remove owner'], 422);
        }

        $taskList->members()->detach($user->id);

        return response()->json(['success' => true, 'message' => 'Member removed']);
    }
}
