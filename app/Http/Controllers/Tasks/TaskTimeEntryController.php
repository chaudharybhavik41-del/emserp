<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskTimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTimeEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.update')->only(['store']);
        $this->middleware('permission:tasks.update|tasks.delete')->only(['destroy']);
    }

    public function store(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'hours' => 'nullable|integer|min:0|max:24',
            'minutes' => 'nullable|integer|min:0|max:59',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'description' => 'nullable|string|max:1000',
            'is_billable' => 'nullable|boolean',
            'hourly_rate' => 'nullable|numeric|min:0|max:999999.99',
        ]);

        $durationMinutes = (int) ($data['duration_minutes'] ?? (((int) ($data['hours'] ?? 0) * 60) + (int) ($data['minutes'] ?? 0)));

        if ($durationMinutes <= 0) {
            $message = 'Please enter a valid time duration.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $entry = $task->timeEntries()->create([
            'user_id' => auth()->id(),
            'description' => $data['description'] ?? null,
            'started_at' => now()->subMinutes($durationMinutes),
            'ended_at' => now(),
            'duration_minutes' => $durationMinutes,
            'is_billable' => (bool) ($data['is_billable'] ?? false),
            'hourly_rate' => $data['hourly_rate'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Time logged successfully.',
                'entry' => $entry->load('user'),
                'task' => $task->fresh(['timeEntries', 'status', 'priority', 'assignee']),
            ]);
        }

        return back()->with('success', 'Time logged successfully.');
    }

    public function destroy(Request $request, Task $task, TaskTimeEntry $timeEntry): RedirectResponse|JsonResponse
    {
        if ((int) $timeEntry->task_id !== (int) $task->id) {
            abort(404);
        }

        if ((int) $timeEntry->user_id !== (int) auth()->id() && !auth()->user()->can('tasks.delete')) {
            $message = 'You are not allowed to delete this time entry.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        $timeEntry->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Time entry deleted successfully.',
                'task' => $task->fresh(['timeEntries']),
            ]);
        }

        return back()->with('success', 'Time entry deleted successfully.');
    }
}
