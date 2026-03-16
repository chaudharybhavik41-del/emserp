<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskIntakeForm;
use App\Models\Tasks\TaskIntakeSubmission;
use App\Services\Tasks\TaskAutomationService;
use App\Services\Tasks\TaskNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TaskIntakeFormController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct(
        protected TaskNotificationService $taskNotificationService,
        protected TaskAutomationService $taskAutomationService,
    )
    {
        $this->middleware('auth')->except(['show', 'submit']);
        $this->middleware('permission:tasks.settings.view')->only(['index']);
        $this->middleware('permission:tasks.settings.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index(): View
    {
        $forms = TaskIntakeForm::query()
            ->forCompany($this->getCompanyId())
            ->with('taskList')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('tasks.settings.intake.index', compact('forms'));
    }

    public function create(): View
    {
        return view('tasks.settings.intake.form', $this->formData(new TaskIntakeForm()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);
        $validated['company_id'] = $this->getCompanyId();
        $validated['created_by'] = auth()->id();

        TaskIntakeForm::create($validated);

        return redirect()->route('task-settings.intake-forms.index')->with('success', 'Intake form created.');
    }

    public function edit(TaskIntakeForm $taskIntakeForm): View
    {
        $this->authorizeForm($taskIntakeForm);

        return view('tasks.settings.intake.form', $this->formData($taskIntakeForm));
    }

    public function update(Request $request, TaskIntakeForm $taskIntakeForm): RedirectResponse
    {
        $this->authorizeForm($taskIntakeForm);
        $taskIntakeForm->update($this->validateForm($request));

        return redirect()->route('task-settings.intake-forms.index')->with('success', 'Intake form updated.');
    }

    public function destroy(TaskIntakeForm $taskIntakeForm): RedirectResponse
    {
        $this->authorizeForm($taskIntakeForm);
        $taskIntakeForm->delete();

        return redirect()->route('task-settings.intake-forms.index')->with('success', 'Intake form deleted.');
    }

    public function show(TaskIntakeForm $taskIntakeForm): View
    {
        abort_unless($taskIntakeForm->is_active, 404);

        if ($taskIntakeForm->requires_auth && !auth()->check()) {
            abort(403);
        }

        return view('tasks.intake.show', compact('taskIntakeForm'));
    }

    public function submit(Request $request, TaskIntakeForm $taskIntakeForm): RedirectResponse
    {
        abort_unless($taskIntakeForm->is_active, 404);

        if ($taskIntakeForm->requires_auth && !auth()->check()) {
            abort(403);
        }

        $fieldRules = [];
        foreach ($taskIntakeForm->fields ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            $fieldRules[$key] = ($field['required'] ?? false ? 'required' : 'nullable') . '|string|max:1000';
        }

        $payload = $request->validate($fieldRules);

        $title = $payload['title'] ?? $taskIntakeForm->name . ' Submission';
        $descriptionLines = collect($taskIntakeForm->fields ?? [])->map(function ($field) use ($payload) {
            $key = $field['key'] ?? null;
            if (!$key || !array_key_exists($key, $payload)) {
                return null;
            }

            return sprintf("%s: %s", $field['label'] ?? Str::headline($key), $payload[$key]);
        })->filter()->values()->all();

        $task = Task::create([
            'company_id' => $taskIntakeForm->company_id,
            'task_list_id' => $taskIntakeForm->task_list_id,
            'title' => $title,
            'description' => implode("\n", $descriptionLines),
            'status_id' => $taskIntakeForm->default_status_id ?: $taskIntakeForm->taskList->default_status_id,
            'priority_id' => $taskIntakeForm->default_priority_id ?: $taskIntakeForm->taskList->default_priority_id,
            'assignee_id' => $taskIntakeForm->default_assignee_id,
            'reporter_id' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        TaskIntakeSubmission::create([
            'task_intake_form_id' => $taskIntakeForm->id,
            'task_id' => $task->id,
            'submitted_by' => auth()->id(),
            'payload' => $payload,
        ]);

        $this->taskNotificationService->notifyAssignment($task->fresh(['assignee', 'taskList', 'project']));
        $this->taskAutomationService->handleTaskEvent($task->fresh(['taskList', 'watchers', 'assignee', 'project']), 'created');

        return redirect()
            ->route('tasks.intake.show', $taskIntakeForm)
            ->with('success', $taskIntakeForm->success_message ?: 'Request submitted successfully.');
    }

    protected function formData(TaskIntakeForm $form): array
    {
        return array_merge($this->taskFilterOptions(new Request()), [
            'form' => $form,
        ]);
    }

    protected function validateForm(Request $request): array
    {
        $validated = $request->validate([
            'task_list_id' => 'required|exists:task_lists,id',
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:180',
            'description' => 'nullable|string',
            'default_status_id' => 'nullable|exists:task_statuses,id',
            'default_priority_id' => 'nullable|exists:task_priorities,id',
            'default_assignee_id' => 'nullable|exists:users,id',
            'success_message' => 'nullable|string|max:500',
            'requires_auth' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'field_labels' => 'required|array|min:1',
            'field_labels.*' => 'required|string|max:80',
            'field_keys' => 'required|array|min:1',
            'field_keys.*' => 'required|string|max:80',
            'field_required' => 'nullable|array',
        ]);

        $fields = [];
        foreach ($validated['field_labels'] as $index => $label) {
            $key = $validated['field_keys'][$index] ?? null;
            if (!$key) {
                continue;
            }

            $fields[] = [
                'label' => $label,
                'key' => Str::slug($key, '_'),
                'required' => in_array((string) $index, array_map('strval', $request->input('field_required', [])), true),
            ];
        }

        return [
            'task_list_id' => $validated['task_list_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'] ?: $validated['name']),
            'description' => $validated['description'] ?? null,
            'fields' => $fields,
            'default_status_id' => $validated['default_status_id'] ?? null,
            'default_priority_id' => $validated['default_priority_id'] ?? null,
            'default_assignee_id' => $validated['default_assignee_id'] ?? null,
            'success_message' => $validated['success_message'] ?? null,
            'requires_auth' => $request->boolean('requires_auth', true),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    protected function authorizeForm(TaskIntakeForm $form): void
    {
        abort_unless((int) $form->company_id === $this->getCompanyId(), 403);
    }
}
