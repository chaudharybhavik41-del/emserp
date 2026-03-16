<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskPlanningController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.view');
    }

    public function calendar(Request $request): View
    {
        $selectedSavedView = $this->resolveSavedView($request);
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', (string) $request->get('month'))->startOfMonth()
            : now()->startOfMonth();

        $gridStart = $month->copy()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();

        $query = $this->visibleTasksQuery(['status', 'priority', 'assignee', 'taskList'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);

        $this->applyTaskFilters($query, $request);

        $tasks = $query->orderBy('due_date')->orderBy('priority_id')->get();
        $tasksByDay = $tasks->groupBy(fn ($task) => $task->due_date?->toDateString());
        $calendarDays = collect(CarbonPeriod::create($gridStart, '1 day', $gridEnd));
        $filterOptions = $this->taskFilterOptions($request);

        return view('tasks.calendar', array_merge($filterOptions, compact(
            'month', 'calendarDays', 'tasksByDay', 'selectedSavedView'
        )));
    }

    public function timeline(Request $request): View
    {
        $selectedSavedView = $this->resolveSavedView($request);

        $query = $this->visibleTasksQuery(['status', 'priority', 'assignee', 'taskList', 'project'])
            ->where(function ($dateQuery) {
                $dateQuery->whereNotNull('start_date')->orWhereNotNull('due_date');
            });

        $this->applyTaskFilters($query, $request);

        $tasks = $query
            ->orderByRaw('COALESCE(start_date, due_date) asc')
            ->limit(120)
            ->get();

        $rangeStart = $tasks->min(fn ($task) => $task->start_date ?? $task->due_date) ?? now()->startOfWeek();
        $rangeEnd = $tasks->max(fn ($task) => $task->due_date ?? $task->start_date) ?? now()->addWeeks(6)->endOfWeek();

        $rangeStart = Carbon::parse($request->get('from', $rangeStart))->startOfWeek();
        $rangeEnd = Carbon::parse($request->get('to', $rangeEnd))->endOfWeek();
        $weeks = collect(CarbonPeriod::create($rangeStart, '1 week', $rangeEnd));
        $rangeDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
        $filterOptions = $this->taskFilterOptions($request);

        return view('tasks.timeline', array_merge($filterOptions, compact(
            'tasks', 'weeks', 'rangeStart', 'rangeEnd', 'rangeDays', 'selectedSavedView'
        )));
    }

    public function workload(Request $request): View
    {
        $query = $this->visibleTasksQuery(['status', 'priority', 'assignee', 'taskList']);
        $this->applyTaskFilters($query, $request);

        $tasks = $query->get();
        $groups = $tasks
            ->groupBy(fn ($task) => $task->assignee_id ?: 'unassigned')
            ->map(function ($groupedTasks, $assigneeKey) {
                $firstTask = $groupedTasks->first();
                $estimatedMinutes = (int) $groupedTasks->sum('estimated_minutes');
                $loggedMinutes = (int) $groupedTasks->sum('logged_minutes');

                return [
                    'assignee_key' => $assigneeKey,
                    'assignee' => $assigneeKey === 'unassigned' ? null : $firstTask?->assignee,
                    'tasks' => $groupedTasks->sortBy([
                        ['due_date', 'asc'],
                        ['priority_id', 'desc'],
                    ]),
                    'open_count' => $groupedTasks->filter->isOpen()->count(),
                    'overdue_count' => $groupedTasks->filter->isOverdue()->count(),
                    'estimated_hours' => round($estimatedMinutes / 60, 2),
                    'logged_hours' => round($loggedMinutes / 60, 2),
                    'remaining_hours' => round(max(0, $estimatedMinutes - $loggedMinutes) / 60, 2),
                    'load_status' => match (true) {
                        $estimatedMinutes >= 2400 => 'high',
                        $estimatedMinutes >= 1200 => 'medium',
                        default => 'low',
                    },
                ];
            })
            ->sortByDesc('estimated_hours')
            ->values();

        $summary = [
            'assignees' => $groups->count(),
            'open_tasks' => $tasks->filter->isOpen()->count(),
            'overdue_tasks' => $tasks->filter->isOverdue()->count(),
            'estimated_hours' => round($tasks->sum('estimated_minutes') / 60, 2),
            'logged_hours' => round($tasks->sum('logged_minutes') / 60, 2),
        ];

        $filterOptions = $this->taskFilterOptions($request);

        return view('tasks.workload', array_merge($filterOptions, compact(
            'groups', 'summary'
        )));
    }
}
