<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    // Helpers
    public function getDescriptionAttribute(): string
    {
        $userName = $this->user?->name ?? 'System';

        return match($this->action) {
            'created' => "{$userName} created this task",
            'updated' => "{$userName} updated {$this->field_name}",
            'status_changed' => $this->formatStatusChange($userName),
            'assigned' => $this->formatAssignmentChange($userName),
            'priority_changed' => $this->formatPriorityChange($userName),
            'commented' => "{$userName} added a comment",
            'time_logged' => "{$userName} logged " . ($this->metadata['duration_formatted'] ?? 'time'),
            'attachment_added' => "{$userName} added an attachment",
            'label_added' => "{$userName} added a label",
            'label_removed' => "{$userName} removed a label",
            'dependency_added' => "{$userName} added a dependency",
            'dependency_removed' => "{$userName} removed a dependency",
            'checklist_added' => "{$userName} added a checklist",
            'checklist_completed' => "{$userName} completed a checklist item",
            'due_date_changed' => $this->formatDueDateChange($userName),
            'archived' => "{$userName} archived this task",
            'unarchived' => "{$userName} unarchived this task",
            default => "{$userName} updated this task",
        };
    }

    protected function formatStatusChange(string $userName): string
    {
        $oldStatus = TaskStatus::find($this->old_value)?->name ?? 'Unknown';
        $newStatus = TaskStatus::find($this->new_value)?->name ?? 'Unknown';

        return "{$userName} changed status from {$oldStatus} to {$newStatus}";
    }

    protected function formatAssignmentChange(string $userName): string
    {
        $oldAssignee = User::find($this->old_value)?->name ?? 'Unassigned';
        $newAssignee = User::find($this->new_value)?->name ?? 'Unassigned';

        if (!$this->old_value) {
            return "{$userName} assigned to {$newAssignee}";
        }

        if (!$this->new_value) {
            return "{$userName} unassigned {$oldAssignee}";
        }

        return "{$userName} reassigned from {$oldAssignee} to {$newAssignee}";
    }

    protected function formatPriorityChange(string $userName): string
    {
        $oldPriority = TaskPriority::find($this->old_value)?->name ?? 'None';
        $newPriority = TaskPriority::find($this->new_value)?->name ?? 'None';

        return "{$userName} changed priority from {$oldPriority} to {$newPriority}";
    }

    protected function formatDueDateChange(string $userName): string
    {
        if (!$this->old_value && $this->new_value) {
            return "{$userName} set due date to {$this->new_value}";
        }

        if ($this->old_value && !$this->new_value) {
            return "{$userName} removed due date";
        }

        return "{$userName} changed due date from {$this->old_value} to {$this->new_value}";
    }

    public function getIconAttribute(): string
    {
        return match($this->action) {
            'created' => 'bi-plus-circle text-success',
            'status_changed' => 'bi-arrow-repeat text-primary',
            'assigned' => 'bi-person text-info',
            'priority_changed' => 'bi-flag text-warning',
            'commented' => 'bi-chat text-secondary',
            'time_logged' => 'bi-clock text-primary',
            'attachment_added' => 'bi-paperclip text-secondary',
            'label_added', 'label_removed' => 'bi-tag text-info',
            'dependency_added', 'dependency_removed' => 'bi-link text-secondary',
            'checklist_added', 'checklist_completed' => 'bi-check-square text-success',
            'due_date_changed' => 'bi-calendar text-warning',
            'archived', 'unarchived' => 'bi-archive text-secondary',
            default => 'bi-pencil text-muted',
        };
    }
}
