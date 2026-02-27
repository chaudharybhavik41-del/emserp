<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_checklist_id',
        'content',
        'is_completed',
        'completed_by',
        'completed_at',
        'assignee_id',
        'due_date',
        'sort_order',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaskChecklistItem $item) {
            // Track completion changes
            if ($item->isDirty('is_completed')) {
                if ($item->is_completed) {
                    $item->completed_by = auth()->id();
                    $item->completed_at = now();
                } else {
                    $item->completed_by = null;
                    $item->completed_at = null;
                }
            }
        });

        static::updated(function (TaskChecklistItem $item) {
            if ($item->wasChanged('is_completed')) {
                $item->checklist->task->logActivity(
                    $item->is_completed ? 'checklist_completed' : 'checklist_uncompleted',
                    null, null, null,
                    ['item_content' => \Str::limit($item->content, 50)]
                );
            }
        });
    }

    // Relationships
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'task_checklist_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    // Helpers
    public function toggle(): void
    {
        $this->update(['is_completed' => !$this->is_completed]);
    }

    public function complete(): void
    {
        $this->update(['is_completed' => true]);
    }

    public function uncomplete(): void
    {
        $this->update(['is_completed' => false]);
    }

    public function isOverdue(): bool
    {
        return $this->due_date 
            && $this->due_date->lt(today()) 
            && !$this->is_completed;
    }
}
