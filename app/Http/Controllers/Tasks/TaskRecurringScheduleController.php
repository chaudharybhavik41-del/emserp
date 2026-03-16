<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Models\Tasks\TaskRecurringSchedule;
use App\Services\Tasks\TaskRecurringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskRecurringScheduleController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.settings.view')->only(['index']);
        $this->middleware('permission:tasks.settings.manage')->except(['index']);
    }

    public function index(): View
    {
        $schedules = TaskRecurringSchedule::query()
            ->forCompany($this->getCompanyId())
            ->with(['taskList', 'template', 'assignee'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('tasks.settings.recurring.index', compact('schedules'));
    }

    public function create(): View
    {
        return view('tasks.settings.recurring.form', $this->formData(new TaskRecurringSchedule()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSchedule($request);
        $validated['company_id'] = $this->getCompanyId();
        $validated['created_by'] = auth()->id();

        TaskRecurringSchedule::create($validated);

        return redirect()->route('task-settings.recurring.index')->with('success', 'Recurring schedule created.');
    }

    public function edit(TaskRecurringSchedule $taskRecurringSchedule): View
    {
        $this->authorizeSchedule($taskRecurringSchedule);

        return view('tasks.settings.recurring.form', $this->formData($taskRecurringSchedule));
    }

    public function update(Request $request, TaskRecurringSchedule $taskRecurringSchedule): RedirectResponse
    {
        $this->authorizeSchedule($taskRecurringSchedule);
        $taskRecurringSchedule->update($this->validateSchedule($request));

        return redirect()->route('task-settings.recurring.index')->with('success', 'Recurring schedule updated.');
    }

    public function destroy(TaskRecurringSchedule $taskRecurringSchedule): RedirectResponse
    {
        $this->authorizeSchedule($taskRecurringSchedule);
        $taskRecurringSchedule->delete();

        return redirect()->route('task-settings.recurring.index')->with('success', 'Recurring schedule deleted.');
    }

    public function runNow(TaskRecurringSchedule $taskRecurringSchedule, TaskRecurringService $recurringService): RedirectResponse
    {
        $this->authorizeSchedule($taskRecurringSchedule);
        $task = $recurringService->createTaskFromSchedule($taskRecurringSchedule->load('taskList', 'template'));

        return redirect()->route('tasks.show', $task)->with('success', 'Recurring task generated.');
    }

    protected function formData(TaskRecurringSchedule $schedule): array
    {
        $options = $this->taskFilterOptions(new Request());

        return array_merge($options, [
            'schedule' => $schedule,
        ]);
    }

    protected function validateSchedule(Request $request): array
    {
        $validated = $request->validate([
            'task_list_id' => 'required|exists:task_lists,id',
            'template_id' => 'nullable|exists:task_templates,id',
            'name' => 'required|string|max:150',
            'title_template' => 'required|string|max:500',
            'description_template' => 'nullable|string',
            'status_id' => 'nullable|exists:task_statuses,id',
            'priority_id' => 'nullable|exists:task_priorities,id',
            'assignee_id' => 'nullable|exists:users,id',
            'task_type' => 'required|string|max:50',
            'estimated_minutes' => 'nullable|integer|min:0',
            'default_labels' => 'nullable|array',
            'default_labels.*' => 'exists:task_labels,id',
            'interval_unit' => 'required|in:daily,weekly,monthly',
            'interval_value' => 'required|integer|min:1|max:30',
            'next_run_at' => 'required|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    protected function authorizeSchedule(TaskRecurringSchedule $schedule): void
    {
        abort_unless((int) $schedule->company_id === $this->getCompanyId(), 403);
    }
}
