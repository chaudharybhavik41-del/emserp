<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\Project;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskLabel;
use App\Models\Tasks\TaskList;
use App\Models\Tasks\TaskPriority;
use App\Models\Tasks\TaskStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskBoardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.view');
    }

    protected function getCompanyId(): int
    {
        return auth()->user()->company_id ?? 1;
    }

    /**
     * Main Kanban board view
     */
    public function index(Request $request): View
    {
        $companyId = $this->getCompanyId();
        $taskListId = $request->get('list');
        $projectId = $request->get('project');
        $bomId = $request->get('bom');

        $query = Task::with(['status', 'priority', 'assignee', 'labels', 'taskList', 'children'])
            ->withCount([
                'comments',
                'children as subtask_count',
                'children as completed_subtask_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', true)),
            ])
            ->forCompany($companyId)
            ->notArchived()
            ->rootTasks();

        if ($taskListId) {
            $query->forList($taskListId);
        }

        if ($projectId) {
            $query->forProject($projectId);
        }

        if ($bomId) {
            $query->where('bom_id', $bomId);
        }

        // Apply filters
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

        if ($labelIds = $request->get('labels')) {
            $query->withLabel(explode(',', $labelIds));
        }

        if ($search = $request->get('q')) {
            $query->search($search);
        }

        $tasks = $query->orderBy('position')->get();
        $boardStats = [
            'total' => $tasks->count(),
            'open' => $tasks->filter(fn($task) => $task->isOpen())->count(),
            'overdue' => $tasks->filter(fn($task) => $task->isOverdue())->count(),
            'unassigned' => $tasks->whereNull('assignee_id')->count(),
        ];

        // Group tasks by status for columns
        $tasksByStatus = $tasks->groupBy('status_id');

        // Get all statuses for columns
        $statuses = TaskStatus::active()->ordered()->get();

        // Filter options
        $taskLists = TaskList::active()->notArchived()->orderBy('name')->get();
        $priorities = TaskPriority::active()->ordered()->get();
        $labels = TaskLabel::active()->orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $selectedProjectId = (int) ($projectId ?: 0);
        if ($selectedProjectId <= 0 && $bomId) {
            $selectedProjectId = (int) (Bom::query()->where('id', (int) $bomId)->value('project_id') ?: 0);
        }
        $boms = $selectedProjectId > 0
            ? Bom::query()->where('project_id', $selectedProjectId)->orderBy('bom_number')->get()
            : collect();

        $currentList = $taskListId ? TaskList::find($taskListId) : null;
        $currentProject = $projectId ? Project::find($projectId) : null;

        return view('tasks.board.index', compact(
            'tasks', 'tasksByStatus', 'statuses', 'taskLists', 'priorities',
            'labels', 'users', 'projects', 'boms', 'currentList', 'currentProject', 'boardStats'
        ));
    }

    /**
     * Move task to different status/position (drag & drop)
     */
    public function moveTask(Request $request): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'status_id' => 'required|exists:task_statuses,id',
            'position' => 'required|integer|min:0',
        ]);

        $task = Task::findOrFail($request->task_id);
        DB::beginTransaction();
        try {
            // Update all positions in the target column
            if ($request->has('column_tasks')) {
                foreach ($request->column_tasks as $index => $taskId) {
                    Task::where('id', $taskId)->update(['position' => $index]);
                }
            }

            // Update the moved task
            $task->update([
                'status_id' => $request->status_id,
                'position' => $request->position,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task moved successfully',
                'task' => $task->fresh(['status', 'priority', 'assignee']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error moving task: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tasks for a specific column (AJAX reload)
     */
    public function getColumnTasks(Request $request, TaskStatus $status): JsonResponse
    {
        $query = Task::with(['priority', 'assignee', 'labels', 'children'])
            ->withCount([
                'comments',
                'children as subtask_count',
                'children as completed_subtask_count' => fn($q) => $q->whereHas('status', fn($s) => $s->where('is_closed', true)),
            ])
            ->forCompany($this->getCompanyId())
            ->notArchived()
            ->rootTasks()
            ->where('status_id', $status->id);

        if ($taskListId = $request->get('list')) {
            $query->forList($taskListId);
        }

        if ($projectId = $request->get('project')) {
            $query->forProject($projectId);
        }

        if ($bomId = $request->get('bom')) {
            $query->where('bom_id', $bomId);
        }

        $tasks = $query->orderBy('position')->get();

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
            'count' => $tasks->count(),
        ]);
    }

    /**
     * Quick create task from board
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:500',
            'status_id' => 'required|exists:task_statuses,id',
            'task_list_id' => 'required|exists:task_lists,id',
            'project_id' => 'nullable|exists:projects,id',
            'assignee_id' => 'nullable|exists:users,id',
            'priority_id' => 'nullable|exists:task_priorities,id',
        ]);

        try {
            // Get max position for the status
            $maxPosition = Task::where('status_id', $request->status_id)
                ->where('task_list_id', $request->task_list_id)
                ->max('position') ?? 0;

            $task = Task::create([
                'company_id' => $this->getCompanyId(),
                'task_list_id' => $request->task_list_id,
                'title' => $request->title,
                'status_id' => $request->status_id,
                'priority_id' => $request->priority_id,
                'assignee_id' => $request->assignee_id,
                'project_id' => $request->project_id,
                'position' => $maxPosition + 1,
            ]);

            $task->load(['status', 'priority', 'assignee', 'labels']);

            return response()->json([
                'success' => true,
                'message' => 'Task created',
                'task' => $task,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating task: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update task from board (inline edit)
     */
    public function quickUpdate(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:500',
            'status_id' => 'sometimes|exists:task_statuses,id',
            'priority_id' => 'sometimes|nullable|exists:task_priorities,id',
            'assignee_id' => 'sometimes|nullable|exists:users,id',
            'due_date' => 'sometimes|nullable|date',
        ]);

        try {
            $task->update($request->only([
                'title', 'status_id', 'priority_id', 'assignee_id', 'due_date'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Task updated',
                'task' => $task->fresh(['status', 'priority', 'assignee', 'labels']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating task: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get board statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Task::forCompany($this->getCompanyId())->notArchived();

        if ($taskListId = $request->get('list')) {
            $query->forList($taskListId);
        }

        if ($projectId = $request->get('project')) {
            $query->forProject($projectId);
        }

        if ($bomId = $request->get('bom')) {
            $query->where('bom_id', $bomId);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->open()->count(),
            'completed' => (clone $query)->closed()->count(),
            'overdue' => (clone $query)->overdue()->count(),
            'due_today' => (clone $query)->dueToday()->count(),
            'unassigned' => (clone $query)->whereNull('assignee_id')->open()->count(),
        ];

        // Tasks by status
        $byStatus = (clone $query)
            ->selectRaw('status_id, COUNT(*) as count')
            ->groupBy('status_id')
            ->pluck('count', 'status_id');

        // Tasks by priority
        $byPriority = (clone $query)
            ->selectRaw('priority_id, COUNT(*) as count')
            ->groupBy('priority_id')
            ->pluck('count', 'priority_id');

        // Tasks by assignee (top 5)
        $byAssignee = (clone $query)
            ->selectRaw('assignee_id, COUNT(*) as count')
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $user = User::find($item->assignee_id);
                return [
                    'assignee_id' => $item->assignee_id,
                    'name' => $user?->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_assignee' => $byAssignee,
        ]);
    }
}
