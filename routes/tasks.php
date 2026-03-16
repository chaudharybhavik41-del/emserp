<?php

use App\Http\Controllers\Tasks\TaskBoardController;
use App\Http\Controllers\Tasks\TaskChecklistController;
use App\Http\Controllers\Tasks\TaskCommentController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskDependencyController;
use App\Http\Controllers\Tasks\TaskInsightsController;
use App\Http\Controllers\Tasks\TaskIntakeFormController;
use App\Http\Controllers\Tasks\TaskLabelController;
use App\Http\Controllers\Tasks\TaskListController;
use App\Http\Controllers\Tasks\TaskAutomationRuleController;
use App\Http\Controllers\Tasks\TaskPlanningController;
use App\Http\Controllers\Tasks\TaskPriorityController;
use App\Http\Controllers\Tasks\TaskRecurringScheduleController;
use App\Http\Controllers\Tasks\TaskSavedFilterController;
use App\Http\Controllers\Tasks\TaskStatusController;
use App\Http\Controllers\Tasks\TaskTemplateController;
use App\Http\Controllers\Tasks\TaskTimeEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Task Management Routes
|--------------------------------------------------------------------------
| Add to routes/web.php: require __DIR__.'/tasks.php';
*/

Route::middleware(['auth'])->prefix('tasks')->name('tasks.')->group(function () {
    Route::get('my-tasks', [TaskController::class, 'myTasks'])->name('my-tasks');
    Route::get('calendar', [TaskPlanningController::class, 'calendar'])->name('calendar');
    Route::get('timeline', [TaskPlanningController::class, 'timeline'])->name('timeline');
    Route::get('workload', [TaskPlanningController::class, 'workload'])->name('workload');
    Route::get('insights', [TaskInsightsController::class, 'dashboard'])->name('insights');
    
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::get('/create', [TaskController::class, 'create'])->name('create');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::get('/{task}', [TaskController::class, 'show'])->name('show');
    Route::get('/{task}/edit', [TaskController::class, 'edit'])->name('edit');
    Route::put('/{task}', [TaskController::class, 'update'])->name('update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');

    Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->name('update-status');
    Route::patch('/{task}/assignee', [TaskController::class, 'updateAssignee'])->name('update-assignee');
    Route::patch('/{task}/schedule', [TaskController::class, 'updateSchedule'])->name('update-schedule');
    Route::post('/{task}/duplicate', [TaskController::class, 'duplicate'])->name('duplicate');
    Route::post('/{task}/watch', [TaskController::class, 'watch'])->name('watch');
    Route::post('/{task}/unwatch', [TaskController::class, 'unwatch'])->name('unwatch');
    Route::post('/{task}/archive', [TaskController::class, 'archive'])->name('archive');
    Route::post('/{task}/unarchive', [TaskController::class, 'unarchive'])->name('unarchive');
    Route::post('/bulk-update', [TaskController::class, 'bulkUpdate'])->name('bulk-update');

    // Comments
    Route::get('/{task}/comments', [TaskCommentController::class, 'index'])->name('comments.index');
    Route::post('/{task}/comments', [TaskCommentController::class, 'store'])->name('comments.store');
    Route::put('/{task}/comments/{comment}', [TaskCommentController::class, 'update'])->name('comments.update');
    Route::post('/{task}/comments/{comment}/toggle-pin', [TaskCommentController::class, 'togglePin'])->name('comments.toggle-pin');
    Route::delete('/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('comments.destroy');

    // Time Entries
    Route::post('/{task}/time-entries', [TaskTimeEntryController::class, 'store'])->name('time-entries.store');
    Route::post('/{task}/time-entries/start', [TaskTimeEntryController::class, 'startTimer'])->name('time-entries.start');
    Route::post('/{task}/time-entries/{timeEntry}/stop', [TaskTimeEntryController::class, 'stopTimer'])->name('time-entries.stop');
    Route::delete('/{task}/time-entries/{timeEntry}', [TaskTimeEntryController::class, 'destroy'])->name('time-entries.destroy');

    // Checklists
    Route::post('/{task}/checklists/{checklist}/items/{item}/toggle', [TaskChecklistController::class, 'toggleItem'])
        ->name('checklists.items.toggle');

    // Dependencies
    Route::post('/{task}/dependencies', [TaskDependencyController::class, 'store'])->name('dependencies.store');
    Route::delete('/{task}/dependencies/{dependency}', [TaskDependencyController::class, 'destroy'])->name('dependencies.destroy');

    // Saved views
    Route::post('/saved-views', [TaskSavedFilterController::class, 'store'])->name('saved-views.store');
    Route::delete('/saved-views/{savedFilter}', [TaskSavedFilterController::class, 'destroy'])->name('saved-views.destroy');
});

Route::prefix('task-intake')->name('tasks.intake.')->group(function () {
    Route::get('/{taskIntakeForm:slug}', [TaskIntakeFormController::class, 'show'])->name('show');
    Route::post('/{taskIntakeForm:slug}', [TaskIntakeFormController::class, 'submit'])->name('submit');
});

Route::middleware(['auth'])->prefix('task-lists')->name('task-lists.')->group(function () {
    Route::get('/', [TaskListController::class, 'index'])->name('index');
    Route::get('/create', [TaskListController::class, 'create'])->name('create');
    Route::post('/', [TaskListController::class, 'store'])->name('store');
    Route::get('/{taskList}', [TaskListController::class, 'show'])->name('show');
    Route::get('/{taskList}/edit', [TaskListController::class, 'edit'])->name('edit');
    Route::put('/{taskList}', [TaskListController::class, 'update'])->name('update');
    Route::delete('/{taskList}', [TaskListController::class, 'destroy'])->name('destroy');
    Route::get('/{taskList}/board', [TaskListController::class, 'board'])->name('board');
    Route::post('/{taskList}/update-positions', [TaskListController::class, 'updatePositions'])->name('update-positions');
    Route::post('/{taskList}/archive', [TaskListController::class, 'archive'])->name('archive');
    Route::post('/{taskList}/unarchive', [TaskListController::class, 'unarchive'])->name('unarchive');
    Route::post('/{taskList}/members', [TaskListController::class, 'addMember'])->name('members.add');
    Route::delete('/{taskList}/members/{user}', [TaskListController::class, 'removeMember'])->name('members.remove');
});

Route::middleware(['auth'])->prefix('task-board')->name('task-board.')->group(function () {
    Route::get('/', [TaskBoardController::class, 'index'])->name('index');
    Route::post('/move', [TaskBoardController::class, 'moveTask'])->name('move');
    Route::post('/quick-create', [TaskBoardController::class, 'quickCreate'])->name('quick-create');
    Route::patch('/{task}/quick-update', [TaskBoardController::class, 'quickUpdate'])->name('quick-update');
    Route::get('/columns/{status}', [TaskBoardController::class, 'getColumnTasks'])->name('columns.show');
    Route::get('/stats', [TaskBoardController::class, 'stats'])->name('stats');
});

// Task Settings Routes
Route::middleware(['auth'])->prefix('task-settings')->name('task-settings.')->group(function () {
    // Statuses
    Route::prefix('statuses')->name('statuses.')->group(function () {
        Route::get('/', [TaskStatusController::class, 'index'])->name('index');
        Route::get('/create', [TaskStatusController::class, 'create'])->name('create');
        Route::post('/', [TaskStatusController::class, 'store'])->name('store');
        Route::get('/{taskStatus}/edit', [TaskStatusController::class, 'edit'])->name('edit');
        Route::put('/{taskStatus}', [TaskStatusController::class, 'update'])->name('update');
        Route::delete('/{taskStatus}', [TaskStatusController::class, 'destroy'])->name('destroy');
        Route::post('/reorder', [TaskStatusController::class, 'reorder'])->name('reorder');
    });

    // Priorities
    Route::prefix('priorities')->name('priorities.')->group(function () {
        Route::get('/', [TaskPriorityController::class, 'index'])->name('index');
        Route::get('/create', [TaskPriorityController::class, 'create'])->name('create');
        Route::post('/', [TaskPriorityController::class, 'store'])->name('store');
        Route::get('/{taskPriority}/edit', [TaskPriorityController::class, 'edit'])->name('edit');
        Route::put('/{taskPriority}', [TaskPriorityController::class, 'update'])->name('update');
        Route::delete('/{taskPriority}', [TaskPriorityController::class, 'destroy'])->name('destroy');
    });

    // Labels
    Route::prefix('labels')->name('labels.')->group(function () {
        Route::get('/', [TaskLabelController::class, 'index'])->name('index');
        Route::get('/create', [TaskLabelController::class, 'create'])->name('create');
        Route::post('/', [TaskLabelController::class, 'store'])->name('store');
        Route::get('/{taskLabel}/edit', [TaskLabelController::class, 'edit'])->name('edit');
        Route::put('/{taskLabel}', [TaskLabelController::class, 'update'])->name('update');
        Route::delete('/{taskLabel}', [TaskLabelController::class, 'destroy'])->name('destroy');
    });

    // Templates
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TaskTemplateController::class, 'index'])->name('index');
        Route::get('/create', [TaskTemplateController::class, 'create'])->name('create');
        Route::post('/', [TaskTemplateController::class, 'store'])->name('store');
        Route::get('/{taskTemplate}/edit', [TaskTemplateController::class, 'edit'])->name('edit');
        Route::put('/{taskTemplate}', [TaskTemplateController::class, 'update'])->name('update');
        Route::delete('/{taskTemplate}', [TaskTemplateController::class, 'destroy'])->name('destroy');
    });

    // Automation rules
    Route::prefix('automations')->name('automations.')->group(function () {
        Route::get('/', [TaskAutomationRuleController::class, 'index'])->name('index');
        Route::get('/create', [TaskAutomationRuleController::class, 'create'])->name('create');
        Route::post('/', [TaskAutomationRuleController::class, 'store'])->name('store');
        Route::get('/{taskAutomationRule}/edit', [TaskAutomationRuleController::class, 'edit'])->name('edit');
        Route::put('/{taskAutomationRule}', [TaskAutomationRuleController::class, 'update'])->name('update');
        Route::delete('/{taskAutomationRule}', [TaskAutomationRuleController::class, 'destroy'])->name('destroy');
    });

    // Recurring schedules
    Route::prefix('recurring')->name('recurring.')->group(function () {
        Route::get('/', [TaskRecurringScheduleController::class, 'index'])->name('index');
        Route::get('/create', [TaskRecurringScheduleController::class, 'create'])->name('create');
        Route::post('/', [TaskRecurringScheduleController::class, 'store'])->name('store');
        Route::get('/{taskRecurringSchedule}/edit', [TaskRecurringScheduleController::class, 'edit'])->name('edit');
        Route::put('/{taskRecurringSchedule}', [TaskRecurringScheduleController::class, 'update'])->name('update');
        Route::delete('/{taskRecurringSchedule}', [TaskRecurringScheduleController::class, 'destroy'])->name('destroy');
        Route::post('/{taskRecurringSchedule}/run-now', [TaskRecurringScheduleController::class, 'runNow'])->name('run-now');
    });

    // Intake forms
    Route::prefix('intake-forms')->name('intake-forms.')->group(function () {
        Route::get('/', [TaskIntakeFormController::class, 'index'])->name('index');
        Route::get('/create', [TaskIntakeFormController::class, 'create'])->name('create');
        Route::post('/', [TaskIntakeFormController::class, 'store'])->name('store');
        Route::get('/{taskIntakeForm}/edit', [TaskIntakeFormController::class, 'edit'])->name('edit');
        Route::put('/{taskIntakeForm}', [TaskIntakeFormController::class, 'update'])->name('update');
        Route::delete('/{taskIntakeForm}', [TaskIntakeFormController::class, 'destroy'])->name('destroy');
    });
});
