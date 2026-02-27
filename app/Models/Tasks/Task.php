<?php

namespace App\Models\Tasks;

use App\Models\Bom;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const TASK_TYPES = [
        'general' => 'General',
        'drawing_review' => 'Drawing Review',
        'material_procurement' => 'Material Procurement',
        'cutting' => 'Cutting',
        'welding' => 'Welding',
        'assembly' => 'Assembly',
        'surface_treatment' => 'Surface Treatment',
        'quality_check' => 'Quality Check',
        'packaging' => 'Packaging',
        'dispatch' => 'Dispatch',
        'installation' => 'Installation',
        'documentation' => 'Documentation',
        'approval' => 'Approval',
        'rework' => 'Rework',
    ];

    protected $fillable = [
        'company_id',
        'task_list_id',
        'parent_id',
        'task_number',
        'title',
        'description',
        'status_id',
        'priority_id',
        'assignee_id',
        'reporter_id',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_minutes',
        'logged_minutes',
        'progress_percent',
        'project_id',
        'bom_id',
        'linkable_type',
        'linkable_id',
        'task_type',
        'position',
        'is_milestone',
        'is_blocked',
        'blocked_reason',
        'is_archived',
        'custom_fields',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'estimated_minutes' => 'integer',
        'logged_minutes' => 'integer',
        'progress_percent' => 'integer',
        'position' => 'integer',
        'is_milestone' => 'boolean',
        'is_blocked' => 'boolean',
        'is_archived' => 'boolean',
        'custom_fields' => 'array',
    ];

    protected $attributes = [
        'progress_percent' => 0,
        'logged_minutes' => 0,
        'task_type' => 'general',
        'is_milestone' => false,
        'is_blocked' => false,
        'is_archived' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            if (empty($task->task_number)) {
                $task->task_number = static::generateTaskNumber();
            }
            if (auth()->check()) {
                $task->created_by = $task->created_by ?? auth()->id();
                $task->updated_by = auth()->id();
                $task->reporter_id = $task->reporter_id ?? auth()->id();
            }
        });

        static::updating(function (Task $task) {
            if (auth()->check()) {
                $task->updated_by = auth()->id();
            }
        });

        static::updated(function (Task $task) {
            // Log status changes
            if ($task->wasChanged('status_id')) {
                $task->logActivity('status_changed', 'status_id', 
                    $task->getOriginal('status_id'), 
                    $task->status_id
                );

                // Mark completed if status is closed
                if ($task->status && $task->status->is_closed && !$task->completed_at) {
                    $task->update(['completed_at' => now()]);
                } elseif ($task->status && !$task->status->is_closed && $task->completed_at) {
                    $task->update(['completed_at' => null]);
                }
            }

            // Log assignee changes
            if ($task->wasChanged('assignee_id')) {
                $task->logActivity('assigned', 'assignee_id',
                    $task->getOriginal('assignee_id'),
                    $task->assignee_id
                );
            }

            // Log priority changes
            if ($task->wasChanged('priority_id')) {
                $task->logActivity('priority_changed', 'priority_id',
                    $task->getOriginal('priority_id'),
                    $task->priority_id
                );
            }
        });

        static::created(function (Task $task) {
            $task->logActivity('created');
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position');
    }

    public function subtasks(): HasMany
    {
        return $this->children();
    }

    public function allDescendants(): HasMany
    {
        return $this->children()->with('allDescendants');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'priority_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label')
            ->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    public function rootComments(): HasMany
    {
        return $this->hasMany(TaskComment::class)
            ->whereNull('parent_id')
            ->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderByDesc('created_at');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('sort_order');
    }

    // Dependencies
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot(['dependency_type', 'lag_days', 'created_by'])
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot(['dependency_type', 'lag_days', 'created_by'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? 1);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForList($query, int $taskListId)
    {
        return $query->where('task_list_id', $taskListId);
    }

    public function scopeRootTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    public function scopeReportedBy($query, int $userId)
    {
        return $query->where('reporter_id', $userId);
    }

    public function scopeWithStatus($query, $statusIds)
    {
        $ids = is_array($statusIds) ? $statusIds : [$statusIds];
        return $query->whereIn('status_id', $ids);
    }

    public function scopeWithPriority($query, $priorityIds)
    {
        $ids = is_array($priorityIds) ? $priorityIds : [$priorityIds];
        return $query->whereIn('priority_id', $ids);
    }

    public function scopeOpen($query)
    {
        return $query->whereHas('status', fn($q) => $q->where('is_closed', false));
    }

    public function scopeClosed($query)
    {
        return $query->whereHas('status', fn($q) => $q->where('is_closed', true));
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->open();
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today())->open();
    }

    public function scopeDueThisWeek($query)
    {
        return $query->whereBetween('due_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->open();
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeMilestones($query)
    {
        return $query->where('is_milestone', true);
    }

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    public function scopeOfType($query, string $taskType)
    {
        return $query->where('task_type', $taskType);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('task_number', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeWithLabel($query, $labelIds)
    {
        $ids = is_array($labelIds) ? $labelIds : [$labelIds];
        return $query->whereHas('labels', fn($q) => $q->whereIn('task_labels.id', $ids));
    }

    // Helpers
    public static function generateTaskNumber(): string
    {
        $year = date('Y');
        $prefix = "TASK-{$year}-";

        $lastNumber = static::where('task_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(task_number, -5) AS UNSIGNED) DESC')
            ->value('task_number');

        if ($lastNumber) {
            $num = (int) substr($lastNumber, -5) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, 5, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->status && !$this->status->is_closed;
    }

    public function isClosed(): bool
    {
        return $this->status && $this->status->is_closed;
    }

    public function isOverdue(): bool
    {
        return $this->due_date 
            && $this->due_date->lt(now()->startOfDay()) 
            && $this->isOpen();
    }

    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
    }

    public function isDueSoon(): bool
    {
        return $this->due_date 
            && $this->due_date->between(now(), now()->addDays(3))
            && $this->isOpen();
    }

    public function getEstimatedHoursAttribute(): ?float
    {
        return $this->estimated_minutes 
            ? round($this->estimated_minutes / 60, 2) 
            : null;
    }

    public function getLoggedHoursAttribute(): float
    {
        return round($this->logged_minutes / 60, 2);
    }

    public function getTimeRemainingMinutesAttribute(): ?int
    {
        if (!$this->estimated_minutes) {
            return null;
        }

        return max(0, $this->estimated_minutes - $this->logged_minutes);
    }

    public function getTimeProgressPercentAttribute(): int
    {
        if (!$this->estimated_minutes || $this->estimated_minutes === 0) {
            return 0;
        }

        return min(100, (int) round(($this->logged_minutes / $this->estimated_minutes) * 100));
    }

    public function getSubtaskCountAttribute(): int
    {
        if (array_key_exists('subtask_count', $this->attributes)) {
            return (int) $this->attributes['subtask_count'];
        }

        if (array_key_exists('children_count', $this->attributes)) {
            return (int) $this->attributes['children_count'];
        }

        return $this->children()->count();
    }

    public function getCompletedSubtaskCountAttribute(): int
    {
        if (array_key_exists('completed_subtask_count', $this->attributes)) {
            return (int) $this->attributes['completed_subtask_count'];
        }

        return $this->children()->closed()->count();
    }

    public function getSubtaskProgressAttribute(): int
    {
        $total = $this->subtask_count;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->completed_subtask_count / $total) * 100);
    }

    public function getChecklistProgressAttribute(): int
    {
        $items = $this->checklists->flatMap->items;
        $total = $items->count();

        if ($total === 0) {
            return 0;
        }

        $completed = $items->where('is_completed', true)->count();
        return (int) round(($completed / $total) * 100);
    }

    public function getTaskTypeNameAttribute(): string
    {
        return self::TASK_TYPES[$this->task_type] ?? $this->task_type;
    }

    public function getTaskTypeColorAttribute(): string
    {
        return match($this->task_type) {
            'drawing_review' => '#8b5cf6',
            'material_procurement' => '#06b6d4',
            'cutting' => '#f97316',
            'welding' => '#ef4444',
            'assembly' => '#3b82f6',
            'surface_treatment' => '#10b981',
            'quality_check' => '#eab308',
            'packaging' => '#a855f7',
            'dispatch' => '#14b8a6',
            'installation' => '#6366f1',
            'documentation' => '#64748b',
            'approval' => '#f59e0b',
            'rework' => '#dc2626',
            default => '#6b7280',
        };
    }

    public function hasBlockingDependencies(): bool
    {
        return $this->dependencies()
            ->whereHas('status', fn($q) => $q->where('is_closed', false))
            ->exists();
    }

    public function getBlockingDependencies(): Collection
    {
        return $this->dependencies()
            ->whereHas('status', fn($q) => $q->where('is_closed', false))
            ->get();
    }

    public function logActivity(string $action, ?string $field = null, $oldValue = null, $newValue = null, ?array $metadata = null): TaskActivity
    {
        return $this->activities()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'field_name' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'metadata' => $metadata,
        ]);
    }

    public function addTimeEntry(int $minutes, ?string $description = null, ?User $user = null): TaskTimeEntry
    {
        $entry = $this->timeEntries()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'description' => $description,
            'started_at' => now()->subMinutes($minutes),
            'ended_at' => now(),
            'duration_minutes' => $minutes,
        ]);

        $this->increment('logged_minutes', $minutes);

        return $entry;
    }

    public function addWatcher(User $user): void
    {
        $this->watchers()->syncWithoutDetaching([$user->id]);
    }

    public function removeWatcher(User $user): void
    {
        $this->watchers()->detach($user->id);
    }

    public function isWatching(User $user): bool
    {
        return $this->watchers()->where('user_id', $user->id)->exists();
    }

    public function duplicateTask(?TaskList $targetList = null): Task
    {
        $newTask = $this->replicate([
            'task_number',
            'completed_at',
            'logged_minutes',
            'progress_percent',
        ]);

        $newTask->task_number = static::generateTaskNumber();
        $newTask->title = $this->title . ' (Copy)';
        $newTask->task_list_id = $targetList?->id ?? $this->task_list_id;
        $newTask->logged_minutes = 0;
        $newTask->progress_percent = 0;
        $newTask->completed_at = null;

        // Reset to default status if available
        if ($defaultStatus = TaskStatus::where('is_default', true)->first()) {
            $newTask->status_id = $defaultStatus->id;
        }

        $newTask->save();

        // Copy labels
        $newTask->labels()->sync($this->labels->pluck('id'));

        // Copy checklists
        foreach ($this->checklists as $checklist) {
            $newChecklist = $newTask->checklists()->create([
                'title' => $checklist->title,
                'sort_order' => $checklist->sort_order,
            ]);

            foreach ($checklist->items as $item) {
                $newChecklist->items()->create([
                    'content' => $item->content,
                    'is_completed' => false,
                    'sort_order' => $item->sort_order,
                ]);
            }
        }

        return $newTask;
    }
}
