<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskChecklist;
use App\Models\Tasks\TaskChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.update');
    }

    public function toggleItem(
        Request $request,
        Task $task,
        TaskChecklist $checklist,
        TaskChecklistItem $item
    ): JsonResponse {
        if ((int) $checklist->task_id !== (int) $task->id || (int) $item->task_checklist_id !== (int) $checklist->id) {
            abort(404);
        }

        $item->toggle();
        $item->refresh();

        $checklist->loadCount([
            'items',
            'items as completed_items_count' => fn($q) => $q->where('is_completed', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => $item->is_completed ? 'Checklist item completed.' : 'Checklist item reopened.',
            'item' => [
                'id' => $item->id,
                'is_completed' => $item->is_completed,
                'completed_at' => $item->completed_at,
            ],
            'checklist' => [
                'id' => $checklist->id,
                'total_items' => (int) $checklist->items_count,
                'completed_items' => (int) $checklist->completed_items_count,
            ],
        ]);
    }
}
