<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\TaskLabel;
use Illuminate\Http\Request;

class TaskLabelController extends Controller
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
        $query = TaskLabel::forCompany($this->getCompanyId())
            ->withCount('tasks');

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'tasks_count', 'is_active', 'created_at'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('name');
        }

        $labels = $query->get();

        return view('tasks.settings.labels.index', compact('labels'));
    }

    public function create()
    {
        return view('tasks.settings.labels.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        $validated['company_id'] = $this->getCompanyId();

        TaskLabel::create($validated);

        return redirect()->route('task-settings.labels.index')
            ->with('success', 'Task label created successfully.');
    }

    public function edit(TaskLabel $taskLabel)
    {
        $this->authorizeCompany($taskLabel);
        return view('tasks.settings.labels.edit', compact('taskLabel'));
    }

    public function update(Request $request, TaskLabel $taskLabel)
    {
        $this->authorizeCompany($taskLabel);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        $taskLabel->update($validated);

        return redirect()->route('task-settings.labels.index')
            ->with('success', 'Task label updated successfully.');
    }

    public function destroy(TaskLabel $taskLabel)
    {
        $this->authorizeCompany($taskLabel);

        // Detach from all tasks first
        $taskLabel->tasks()->detach();
        $taskLabel->delete();

        return redirect()->route('task-settings.labels.index')
            ->with('success', 'Task label deleted successfully.');
    }

    protected function authorizeCompany(TaskLabel $taskLabel)
    {
        if ($taskLabel->company_id !== $this->getCompanyId()) {
            abort(403);
        }
    }
}


