<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskAttachment;
use App\Models\Tasks\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:tasks.view');
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string|max:10000',
            'parent_id' => 'nullable|exists:task_comments,id',
            'is_internal' => 'boolean',
        ]);

        $comment = $task->comments()->create([
            'user_id' => auth()->id(),
            'content' => $data['content'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('task-attachments/' . $task->id, 'public');
                
                $task->attachments()->create([
                    'task_comment_id' => $comment->id,
                    'user_id' => auth()->id(),
                    'filename' => basename($path),
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'path' => $path,
                ]);
            }
        }

        if ($request->expectsJson()) {
            $comment->load('user', 'attachments');
            return response()->json([
                'success' => true,
                'message' => 'Comment added',
                'comment' => $comment,
            ]);
        }

        return back()->with('success', 'Comment added.');
    }

    /**
     * Update a comment
     */
    public function update(Request $request, Task $task, TaskComment $comment): RedirectResponse|JsonResponse
    {
        // Check if user can edit
        if (!$comment->canEdit(auth()->user())) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'You cannot edit this comment.');
        }

        $data = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $comment->update([
            'content' => $data['content'],
            'edited_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment updated',
                'comment' => $comment->fresh('user'),
            ]);
        }

        return back()->with('success', 'Comment updated.');
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, Task $task, TaskComment $comment): RedirectResponse|JsonResponse
    {
        // Check if user can delete
        if (!$comment->canDelete(auth()->user()) && !auth()->user()->can('tasks.delete')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'You cannot delete this comment.');
        }

        // Delete attachments
        foreach ($comment->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        }

        $comment->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment deleted',
            ]);
        }

        return back()->with('success', 'Comment deleted.');
    }

    /**
     * Toggle pin status
     */
    public function togglePin(Task $task, TaskComment $comment): JsonResponse
    {
        $comment->togglePin();

        return response()->json([
            'success' => true,
            'message' => $comment->is_pinned ? 'Comment pinned' : 'Comment unpinned',
            'is_pinned' => $comment->is_pinned,
        ]);
    }

    /**
     * Get comments for a task (AJAX)
     */
    public function index(Request $request, Task $task): JsonResponse
    {
        $comments = $task->comments()
            ->with(['user', 'replies.user', 'attachments'])
            ->whereNull('parent_id')
            ->orderBy($request->get('sort', 'created_at'), $request->get('dir', 'asc'))
            ->get();

        return response()->json([
            'success' => true,
            'comments' => $comments,
            'count' => $comments->count(),
        ]);
    }
}
