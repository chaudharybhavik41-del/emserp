<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tasks\Concerns\InteractsWithTaskModule;
use App\Models\Tasks\TaskSavedFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskSavedFilterController extends Controller
{
    use InteractsWithTaskModule;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.view');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'view_type' => 'required|in:list,board,calendar,gantt,table',
            'is_default' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            TaskSavedFilter::query()
                ->forCompany($this->getCompanyId())
                ->where('user_id', $this->taskUser()->id)
                ->update(['is_default' => false]);
        }

        $savedFilter = TaskSavedFilter::create([
            'company_id' => $this->getCompanyId(),
            'user_id' => $this->taskUser()->id,
            'task_list_id' => $request->filled('list') ? (int) $request->input('list') : null,
            'name' => $validated['name'],
            'filters' => array_filter($this->taskFiltersFromRequest($request), function ($value) {
                if (is_array($value)) {
                    return $value !== [];
                }

                return $value !== null && $value !== false && $value !== '';
            }),
            'view_type' => $validated['view_type'],
            'sort_by' => [
                'sort' => $request->input('sort', 'created_at'),
                'dir' => $request->input('dir', 'desc'),
            ],
            'is_default' => $request->boolean('is_default'),
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return redirect()->route($savedFilter->targetRouteName(), [
            'saved_view' => $savedFilter->id,
        ])->with('success', "Saved view '{$savedFilter->name}' created.");
    }

    public function destroy(TaskSavedFilter $savedFilter): RedirectResponse
    {
        abort_unless(
            (int) $savedFilter->company_id === $this->getCompanyId()
            && ((int) $savedFilter->user_id === (int) $this->taskUser()->id || $this->taskUser()->can('tasks.settings.manage')),
            403
        );

        $routeName = $savedFilter->targetRouteName();
        $savedFilter->delete();

        return redirect()->route($routeName)->with('success', 'Saved view deleted.');
    }
}
