<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskPriority;
use App\Models\Tasks\TaskStatus;
use App\Models\Tasks\TaskTemplate;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:tasks.settings.view')->only(['index']);
        $this->middleware('can:tasks.settings.manage')->except(['index']);
    }

    protected function getCompanyId(): int
    {
        return auth()->user()->company_id ?? 1;
    }
    public function index(Request $request)
    {
        $taskTypes = Task::TASK_TYPES;

        $query = TaskTemplate::forCompany($this->getCompanyId())
            ->with(['defaultStatus', 'defaultPriority', 'createdBy']);

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('task_type', 'like', $like)
                    ->orWhere('title_template', 'like', $like);
            });
        }

        $taskType = $request->get('task_type');
        if ($taskType !== null && $taskType !== '') {
            $query->where('task_type', $taskType);
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'task_type', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('name');
        }

        $templates = $query->get();

        return view('tasks.settings.templates.index', compact('templates', 'taskTypes'));
    }

    public function create()
    {
        $companyId = $this->getCompanyId();
        $statuses = TaskStatus::forCompany($companyId)->active()->ordered()->get();
        $priorities = TaskPriority::forCompany($companyId)->active()->ordered()->get();
        $taskTypes = Task::TASK_TYPES;

        return view('tasks.settings.templates.create', compact('statuses', 'priorities', 'taskTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'title_template' => 'nullable|string|max:500',
            'description_template' => 'nullable|string',
            'default_status_id' => 'nullable|exists:task_statuses,id',
            'default_priority_id' => 'nullable|exists:task_priorities,id',
            'estimated_minutes' => 'nullable|integer|min:0',
            'default_checklist' => 'nullable|array',
            'default_labels' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $validated['company_id'] = $this->getCompanyId();
        $validated['created_by'] = auth()->id();

        TaskTemplate::create($validated);

        return redirect()->route('task-settings.templates.index')
            ->with('success', 'Task template created successfully.');
    }

    public function edit(TaskTemplate $taskTemplate)
    {
        $this->authorizeCompany($taskTemplate);

        $companyId = $this->getCompanyId();
        $statuses = TaskStatus::forCompany($companyId)->active()->ordered()->get();
        $priorities = TaskPriority::forCompany($companyId)->active()->ordered()->get();
        $taskTypes = Task::TASK_TYPES;

        return view('tasks.settings.templates.edit', compact('taskTemplate', 'statuses', 'priorities', 'taskTypes'));
    }

    public function update(Request $request, TaskTemplate $taskTemplate)
    {
        $this->authorizeCompany($taskTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'nullable|string|max:50',
            'title_template' => 'nullable|string|max:500',
            'description_template' => 'nullable|string',
            'default_status_id' => 'nullable|exists:task_statuses,id',
            'default_priority_id' => 'nullable|exists:task_priorities,id',
            'estimated_minutes' => 'nullable|integer|min:0',
            'default_checklist' => 'nullable|array',
            'default_labels' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $taskTemplate->update($validated);

        return redirect()->route('task-settings.templates.index')
            ->with('success', 'Task template updated successfully.');
    }

    public function destroy(TaskTemplate $taskTemplate)
    {
        $this->authorizeCompany($taskTemplate);

        $taskTemplate->delete();

        return redirect()->route('task-settings.templates.index')
            ->with('success', 'Task template deleted successfully.');
    }

    protected function authorizeCompany(TaskTemplate $taskTemplate)
    {
        if ($taskTemplate->company_id !== $this->getCompanyId()) {
            abort(403);
        }
    }
}


