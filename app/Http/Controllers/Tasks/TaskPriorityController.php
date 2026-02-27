<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\TaskPriority;
use Illuminate\Http\Request;

class TaskPriorityController extends Controller
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
        $query = TaskPriority::forCompany($this->getCompanyId())
            ->withCount('tasks');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $default = $request->get('default');
        if ($default === 'default') {
            $query->where('is_default', true);
        } elseif ($default === 'non_default') {
            $query->where('is_default', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'level', 'sort_order', 'tasks_count', 'is_default', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour (manual/level ordering)
            $query->ordered();
        }

        $priorities = $query->get();

        return view('tasks.settings.priorities.index', compact('priorities'));
    }

    public function create()
    {
        return view('tasks.settings.priorities.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'level' => 'required|integer|min:0|max:10',
            'is_default' => 'boolean',
        ]);

        $companyId = $this->getCompanyId();
        $validated['company_id'] = $companyId;

        // If setting as default, remove default from others
        if ($request->boolean('is_default')) {
            TaskPriority::forCompany($companyId)->update(['is_default' => false]);
        }

        TaskPriority::create($validated);

        return redirect()->route('task-settings.priorities.index')
            ->with('success', 'Task priority created successfully.');
    }

    public function edit(TaskPriority $taskPriority)
    {
        $this->authorizeCompany($taskPriority);
        return view('tasks.settings.priorities.edit', compact('taskPriority'));
    }

    public function update(Request $request, TaskPriority $taskPriority)
    {
        $this->authorizeCompany($taskPriority);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'level' => 'required|integer|min:0|max:10',
            'is_default' => 'boolean',
        ]);

        // If setting as default, remove default from others
        if ($request->boolean('is_default') && !$taskPriority->is_default) {
            TaskPriority::forCompany($this->getCompanyId())->update(['is_default' => false]);
        }

        $taskPriority->update($validated);

        return redirect()->route('task-settings.priorities.index')
            ->with('success', 'Task priority updated successfully.');
    }

    public function destroy(TaskPriority $taskPriority)
    {
        $this->authorizeCompany($taskPriority);

        if ($taskPriority->tasks()->count() > 0) {
            return back()->with('error', 'Cannot delete priority with existing tasks.');
        }

        if ($taskPriority->is_default) {
            return back()->with('error', 'Cannot delete the default priority.');
        }

        $taskPriority->delete();

        return redirect()->route('task-settings.priorities.index')
            ->with('success', 'Task priority deleted successfully.');
    }

    protected function authorizeCompany(TaskPriority $taskPriority)
    {
        if ($taskPriority->company_id !== $this->getCompanyId()) {
            abort(403);
        }
    }
}


