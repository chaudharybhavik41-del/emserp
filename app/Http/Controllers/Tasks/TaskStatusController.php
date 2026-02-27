<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\TaskStatus;
use Illuminate\Http\Request;

class TaskStatusController extends Controller
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
        $query = TaskStatus::forCompany($this->getCompanyId())
            ->withCount('tasks');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        $category = $request->get('category');
        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        $closed = $request->get('closed');
        if ($closed === 'open') {
            $query->where('is_closed', false);
        } elseif ($closed === 'closed') {
            $query->where('is_closed', true);
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

        $sortable = ['name', 'category', 'sort_order', 'tasks_count', 'is_default', 'is_closed', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour (manual ordering)
            $query->ordered();
        }

        $statuses = $query->get();

        return view('tasks.settings.statuses.index', compact('statuses'));
    }

    public function create()
    {
        return view('tasks.settings.statuses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
            'color' => 'required|string|max:20',
            'icon' => 'nullable|string|max:50',
            'category' => 'required|in:not_started,in_progress,completed,cancelled',
            'is_default' => 'boolean',
            'is_closed' => 'boolean',
        ]);

        $companyId = $this->getCompanyId();
        $validated['company_id'] = $companyId;
        $validated['slug'] = $validated['slug'] ?? \Str::slug($validated['name']);
        $validated['sort_order'] = TaskStatus::forCompany($companyId)->max('sort_order') + 1;

        // If setting as default, remove default from others
        if ($request->boolean('is_default')) {
            TaskStatus::forCompany($companyId)->update(['is_default' => false]);
        }

        TaskStatus::create($validated);

        return redirect()->route('task-settings.statuses.index')
            ->with('success', 'Task status created successfully.');
    }

    public function edit(TaskStatus $taskStatus)
    {
        $this->authorizeCompany($taskStatus);
        return view('tasks.settings.statuses.edit', compact('taskStatus'));
    }

    public function update(Request $request, TaskStatus $taskStatus)
    {
        $this->authorizeCompany($taskStatus);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100',
            'color' => 'required|string|max:20',
            'icon' => 'nullable|string|max:50',
            'category' => 'required|in:not_started,in_progress,completed,cancelled',
            'is_default' => 'boolean',
            'is_closed' => 'boolean',
        ]);

        // If setting as default, remove default from others
        if ($request->boolean('is_default') && !$taskStatus->is_default) {
            TaskStatus::forCompany($this->getCompanyId())->update(['is_default' => false]);
        }

        $taskStatus->update($validated);

        return redirect()->route('task-settings.statuses.index')
            ->with('success', 'Task status updated successfully.');
    }

    public function destroy(TaskStatus $taskStatus)
    {
        $this->authorizeCompany($taskStatus);

        if ($taskStatus->tasks()->count() > 0) {
            return back()->with('error', 'Cannot delete status with existing tasks.');
        }

        if ($taskStatus->is_default) {
            return back()->with('error', 'Cannot delete the default status.');
        }

        $taskStatus->delete();

        return redirect()->route('task-settings.statuses.index')
            ->with('success', 'Task status deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:task_statuses,id',
        ]);

        $companyId = $this->getCompanyId();
        foreach ($request->order as $index => $id) {
            TaskStatus::where('id', $id)
                ->where('company_id', $companyId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    protected function authorizeCompany(TaskStatus $taskStatus)
    {
        $companyId = $this->getCompanyId();
        if ($taskStatus->company_id !== $companyId) {
            abort(403);
        }
    }
}


