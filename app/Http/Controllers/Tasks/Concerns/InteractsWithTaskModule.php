<?php

namespace App\Http\Controllers\Tasks\Concerns;

use App\Models\Bom;
use App\Models\Company;
use App\Models\Project;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskLabel;
use App\Models\Tasks\TaskList;
use App\Models\Tasks\TaskPriority;
use App\Models\Tasks\TaskSavedFilter;
use App\Models\Tasks\TaskStatus;
use App\Models\Tasks\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait InteractsWithTaskModule
{
    protected function getCompanyId(): int
    {
        $userCompanyId = data_get(auth()->user(), 'company_id');

        if ($userCompanyId) {
            return (int) $userCompanyId;
        }

        return (int) (Company::query()->where('is_default', true)->value('id')
            ?: Company::query()->orderBy('id')->value('id')
            ?: 1);
    }

    protected function taskUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function visibleTaskListsQuery(): Builder
    {
        return TaskList::query()
            ->forCompany($this->getCompanyId())
            ->active()
            ->notArchived()
            ->visibleTo($this->taskUser());
    }

    protected function visibleTasksQuery(array $with = []): Builder
    {
        return Task::query()
            ->with($with)
            ->forCompany($this->getCompanyId())
            ->visibleToUser($this->taskUser())
            ->notArchived();
    }

    protected function resolveSavedView(Request $request): ?TaskSavedFilter
    {
        $savedViewId = (int) $request->get('saved_view');

        if ($savedViewId <= 0) {
            return null;
        }

        $savedView = TaskSavedFilter::query()
            ->forCompany($this->getCompanyId())
            ->forUser($this->taskUser()->id)
            ->findOrFail($savedViewId);

        $filters = $savedView->filters ?? [];
        $merge = [];

        foreach ($filters as $key => $value) {
            if (!$request->exists($key) || $request->get($key) === '' || $request->get($key) === null) {
                $merge[$key] = $value;
            }
        }

        $sort = $savedView->sort_by ?? [];
        if (!empty($sort['sort']) && !$request->filled('sort')) {
            $merge['sort'] = $sort['sort'];
        }
        if (!empty($sort['dir']) && !$request->filled('dir')) {
            $merge['dir'] = $sort['dir'];
        }

        if (!empty($merge)) {
            $request->merge($merge);
        }

        return $savedView;
    }

    protected function taskSavedViews(): Collection
    {
        return TaskSavedFilter::query()
            ->forCompany($this->getCompanyId())
            ->forUser($this->taskUser()->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    protected function taskFiltersFromRequest(Request $request): array
    {
        return [
            'task_list_id' => $request->get('list'),
            'project_id' => $request->get('project'),
            'bom_id' => $request->get('bom'),
            'status_ids' => $this->normalizeFilterIds($request->get('status')),
            'priority_ids' => $this->normalizeFilterIds($request->get('priority')),
            'assignee_ids' => $this->normalizeAssigneeFilter($request->get('assignee')),
            'label_ids' => $this->normalizeFilterIds($request->get('labels')),
            'task_types' => $this->normalizeFilterIds($request->get('type')),
            'search' => $request->get('q'),
            'overdue' => $request->boolean('overdue'),
            'due_today' => $request->boolean('due_today'),
            'due_this_week' => $request->boolean('due_this_week'),
            'include_subtasks' => $request->boolean('include_subtasks'),
        ];
    }

    protected function applyTaskFilters(Builder $query, Request $request): Builder
    {
        if ($taskListId = $request->get('list')) {
            $query->forList((int) $taskListId);
        }

        if ($projectId = $request->get('project')) {
            $query->forProject((int) $projectId);
        }

        if ($bomId = $request->get('bom')) {
            $query->where('bom_id', (int) $bomId);
        }

        if ($statusIds = $this->normalizeFilterIds($request->get('status'))) {
            $query->withStatus($statusIds);
        }

        if ($priorityIds = $this->normalizeFilterIds($request->get('priority'))) {
            $query->withPriority($priorityIds);
        }

        if ($assigneeFilter = $request->get('assignee')) {
            if ($assigneeFilter === 'me') {
                $query->assignedTo($this->taskUser()->id);
            } elseif ($assigneeFilter === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->assignedTo((int) $assigneeFilter);
            }
        }

        if ($taskType = $request->get('type')) {
            $types = is_array($taskType) ? $taskType : explode(',', (string) $taskType);
            $query->whereIn('task_type', array_filter($types));
        }

        if ($labelIds = $this->normalizeFilterIds($request->get('labels'))) {
            $query->withLabel($labelIds);
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->search($search);
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        } elseif ($request->boolean('due_today')) {
            $query->dueToday();
        } elseif ($request->boolean('due_this_week')) {
            $query->dueThisWeek();
        }

        if (!$request->boolean('include_subtasks')) {
            $query->rootTasks();
        }

        return $query;
    }

    protected function taskFilterOptions(Request $request): array
    {
        $selectedProjectId = (int) ($request->get('project') ?: 0);

        if ($selectedProjectId <= 0 && $request->filled('bom')) {
            $selectedProjectId = (int) (Bom::query()->where('id', (int) $request->get('bom'))->value('project_id') ?: 0);
        }

        return [
            'taskLists' => $this->visibleTaskListsQuery()->orderBy('name')->get(),
            'statuses' => TaskStatus::query()->forCompany($this->getCompanyId())->active()->ordered()->get(),
            'priorities' => TaskPriority::query()->forCompany($this->getCompanyId())->active()->ordered()->get(),
            'labels' => TaskLabel::query()->forCompany($this->getCompanyId())->active()->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'boms' => $selectedProjectId > 0
                ? Bom::query()->where('project_id', $selectedProjectId)->orderBy('bom_number')->get()
                : collect(),
            'templates' => TaskTemplate::query()->forCompany($this->getCompanyId())->active()->orderBy('name')->get(),
            'savedViews' => $this->taskSavedViews(),
        ];
    }

    protected function authorizeTaskView(Task $task): void
    {
        $task->loadMissing('taskList');

        abort_unless($task->canView($this->taskUser(), $this->getCompanyId()), 403);
    }

    protected function authorizeTaskEdit(Task $task): void
    {
        $task->loadMissing('taskList');

        abort_unless($task->canEdit($this->taskUser(), $this->getCompanyId()), 403);
    }

    protected function authorizeTaskListView(TaskList $taskList): void
    {
        abort_unless(
            (int) $taskList->company_id === $this->getCompanyId() && $taskList->canView($this->taskUser()),
            403
        );
    }

    protected function authorizeTaskListEdit(TaskList $taskList): void
    {
        abort_unless(
            (int) $taskList->company_id === $this->getCompanyId() && $taskList->canEdit($this->taskUser()),
            403
        );
    }

    protected function normalizeFilterIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(explode(',', $value), fn ($item) => $item !== ''));
        }

        return $value !== null && $value !== '' ? [(string) $value] : [];
    }

    protected function normalizeAssigneeFilter(mixed $value): array
    {
        if ($value === 'me') {
            return [$this->taskUser()->id];
        }

        if ($value === 'unassigned') {
            return ['unassigned'];
        }

        return $this->normalizeFilterIds($value);
    }
}
