<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskTimeEntry;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskInsightsController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.reports.view|tasks.view');
    }

    public function dashboard(Request $request): View
    {
        $query = $this->visibleTasksQuery(['status', 'assignee', 'taskList', 'project']);
        $this->applyTaskFilters($query, $request);

        $tasks = $query->get();
        $completedTasks = $tasks->filter(fn (Task $task) => $task->completed_at !== null);

        $throughputTrend = collect(CarbonPeriod::create(now()->subDays(13), '1 day', now()))
            ->map(fn ($date) => [
                'date' => $date->format('d M'),
                'count' => $completedTasks->filter(fn ($task) => optional($task->completed_at)->isSameDay($date))->count(),
            ]);

        $cycleHours = $completedTasks
            ->filter(fn ($task) => $task->created_at && $task->completed_at)
            ->map(fn ($task) => round($task->created_at->diffInHours($task->completed_at), 2));

        $leadHours = $completedTasks
            ->filter(fn ($task) => $task->start_date && $task->completed_at)
            ->map(fn ($task) => round($task->start_date->startOfDay()->diffInHours($task->completed_at), 2));

        $assigneePerformance = $completedTasks
            ->groupBy(fn ($task) => $task->assignee?->name ?: 'Unassigned')
            ->map(fn ($group, $name) => [
                'name' => $name,
                'completed' => $group->count(),
                'avg_cycle_hours' => round($group
                    ->filter(fn ($task) => $task->created_at && $task->completed_at)
                    ->avg(fn ($task) => $task->created_at->diffInHours($task->completed_at)) ?: 0, 2),
            ])
            ->sortByDesc('completed')
            ->values();

        $timeUtilization = TaskTimeEntry::query()
            ->whereIn('task_id', $tasks->pluck('id'))
            ->selectRaw('SUM(duration_minutes) as minutes, SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END) as billable_minutes')
            ->first();

        $summary = [
            'open_tasks' => $tasks->filter->isOpen()->count(),
            'completed_tasks' => $completedTasks->count(),
            'overdue_tasks' => $tasks->filter->isOverdue()->count(),
            'avg_cycle_hours' => round($cycleHours->avg() ?: 0, 2),
            'avg_lead_hours' => round($leadHours->avg() ?: 0, 2),
            'billable_hours' => round(((int) ($timeUtilization->billable_minutes ?? 0)) / 60, 2),
            'logged_hours' => round(((int) ($timeUtilization->minutes ?? 0)) / 60, 2),
        ];

        $filterOptions = $this->taskFilterOptions($request);

        return view('tasks.insights.dashboard', array_merge($filterOptions, compact(
            'summary', 'throughputTrend', 'assigneePerformance'
        )));
    }
}
