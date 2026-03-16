<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Models\Tasks\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskDependencyController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.update');
    }

    public function store(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $this->authorizeTaskEdit($task);

        $data = $request->validate([
            'depends_on_task_id' => 'required|exists:tasks,id',
            'dependency_type' => 'required|in:finish_to_start,start_to_start,finish_to_finish,start_to_finish',
            'lag_days' => 'nullable|integer|min:0|max:365',
        ]);

        $dependency = $this->visibleTasksQuery(['dependencies'])->findOrFail((int) $data['depends_on_task_id']);

        if ($task->wouldCreateDependencyCycle($dependency)) {
            return back()->with('error', 'This dependency would create a circular dependency chain.');
        }

        $task->dependencies()->syncWithoutDetaching([
            $dependency->id => [
                'dependency_type' => $data['dependency_type'],
                'lag_days' => (int) ($data['lag_days'] ?? 0),
                'created_by' => auth()->id(),
            ],
        ]);

        $task->logActivity('dependency_added', null, null, $dependency->task_number, [
            'depends_on_task_id' => $dependency->id,
            'dependency_type' => $data['dependency_type'],
            'lag_days' => (int) ($data['lag_days'] ?? 0),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Dependency added.',
            ]);
        }

        return back()->with('success', 'Dependency added.');
    }

    public function destroy(Request $request, Task $task, Task $dependency): RedirectResponse|JsonResponse
    {
        $this->authorizeTaskEdit($task);
        $this->authorizeTaskView($dependency);

        $task->dependencies()->detach($dependency->id);
        $task->logActivity('dependency_removed', null, $dependency->task_number, null, [
            'depends_on_task_id' => $dependency->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Dependency removed.',
            ]);
        }

        return back()->with('success', 'Dependency removed.');
    }
}
