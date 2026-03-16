<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Models\Tasks\TaskAutomationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskAutomationRuleController extends Controller
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
        $rules = TaskAutomationRule::query()
            ->forCompany($this->getCompanyId())
            ->with('taskList')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('tasks.settings.automations.index', compact('rules'));
    }

    public function create(): View
    {
        return view('tasks.settings.automations.form', array_merge($this->formOptions(), [
            'rule' => new TaskAutomationRule(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRule($request);
        $validated['company_id'] = $this->getCompanyId();
        $validated['created_by'] = auth()->id();

        TaskAutomationRule::create($validated);

        return redirect()->route('task-settings.automations.index')
            ->with('success', 'Automation rule created.');
    }

    public function edit(TaskAutomationRule $taskAutomationRule): View
    {
        $this->authorizeRule($taskAutomationRule);

        return view('tasks.settings.automations.form', array_merge($this->formOptions(), [
            'rule' => $taskAutomationRule,
        ]));
    }

    public function update(Request $request, TaskAutomationRule $taskAutomationRule): RedirectResponse
    {
        $this->authorizeRule($taskAutomationRule);
        $taskAutomationRule->update($this->validateRule($request));

        return redirect()->route('task-settings.automations.index')
            ->with('success', 'Automation rule updated.');
    }

    public function destroy(TaskAutomationRule $taskAutomationRule): RedirectResponse
    {
        $this->authorizeRule($taskAutomationRule);
        $taskAutomationRule->delete();

        return redirect()->route('task-settings.automations.index')
            ->with('success', 'Automation rule deleted.');
    }

    protected function validateRule(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'task_list_id' => 'nullable|exists:task_lists,id',
            'trigger_event' => 'required|in:created,updated,status_changed,comment_added,overdue',
            'condition_status_id' => 'nullable|exists:task_statuses,id',
            'condition_priority_id' => 'nullable|exists:task_priorities,id',
            'condition_task_type' => 'nullable|string|max:50',
            'condition_title_contains' => 'nullable|string|max:120',
            'condition_has_assignee' => 'nullable|boolean',
            'action_set_status_id' => 'nullable|exists:task_statuses,id',
            'action_set_priority_id' => 'nullable|exists:task_priorities,id',
            'action_assign_user_id' => 'nullable|exists:users,id',
            'action_add_watcher_ids' => 'nullable|array',
            'action_add_watcher_ids.*' => 'exists:users,id',
            'action_notify_user_ids' => 'nullable|array',
            'action_notify_user_ids.*' => 'exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        return [
            'name' => $validated['name'],
            'task_list_id' => $validated['task_list_id'] ?? null,
            'trigger_event' => $validated['trigger_event'],
            'conditions' => array_filter([
                'status_id' => $validated['condition_status_id'] ?? null,
                'priority_id' => $validated['condition_priority_id'] ?? null,
                'task_type' => $validated['condition_task_type'] ?? null,
                'title_contains' => $validated['condition_title_contains'] ?? null,
                'has_assignee' => $request->has('condition_has_assignee') ? $request->boolean('condition_has_assignee') : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'actions' => array_filter([
                'set_status_id' => $validated['action_set_status_id'] ?? null,
                'set_priority_id' => $validated['action_set_priority_id'] ?? null,
                'assign_user_id' => $validated['action_assign_user_id'] ?? null,
                'add_watcher_ids' => $validated['action_add_watcher_ids'] ?? null,
                'notify_user_ids' => $validated['action_notify_user_ids'] ?? null,
            ], fn ($value) => $value !== null && $value !== [] && $value !== ''),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    protected function authorizeRule(TaskAutomationRule $rule): void
    {
        abort_unless((int) $rule->company_id === $this->getCompanyId(), 403);
    }

    protected function formOptions(): array
    {
        return [
            'taskLists' => $this->visibleTaskListsQuery()->orderBy('name')->get(),
            'users' => $this->taskFilterOptions(new Request())['users'],
            'statuses' => $this->taskFilterOptions(new Request())['statuses'],
            'priorities' => $this->taskFilterOptions(new Request())['priorities'],
        ];
    }
}
