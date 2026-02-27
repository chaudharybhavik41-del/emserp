<?php

namespace App\Models\Tasks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TaskSavedFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'task_list_id',
        'name',
        'filters',
        'view_type',
        'columns',
        'sort_by',
        'is_default',
        'is_shared',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'sort_by' => 'array',
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('is_shared', true);
        });
    }

    public function applyFilters(Builder $query): Builder
    {
        $filters = $this->filters ?? [];

        if (!empty($filters['status_ids'])) {
            $query->whereIn('status_id', $filters['status_ids']);
        }

        if (!empty($filters['priority_ids'])) {
            $query->whereIn('priority_id', $filters['priority_ids']);
        }

        if (!empty($filters['assignee_ids'])) {
            $query->whereIn('assignee_id', $filters['assignee_ids']);
        }

        if (!empty($filters['label_ids'])) {
            $query->whereHas('labels', fn($q) => $q->whereIn('task_labels.id', $filters['label_ids']));
        }

        if (!empty($filters['task_types'])) {
            $query->whereIn('task_type', $filters['task_types']);
        }

        if (!empty($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (!empty($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }

        return $query;
    }

    public static function getPresetFilters(): array
    {
        return [
            'my_tasks' => ['name' => 'My Tasks', 'icon' => 'bi-person'],
            'overdue' => ['name' => 'Overdue', 'icon' => 'bi-exclamation-triangle'],
            'due_today' => ['name' => 'Due Today', 'icon' => 'bi-calendar-day'],
            'due_this_week' => ['name' => 'Due This Week', 'icon' => 'bi-calendar-week'],
            'unassigned' => ['name' => 'Unassigned', 'icon' => 'bi-person-x'],
            'high_priority' => ['name' => 'High Priority', 'icon' => 'bi-flag'],
        ];
    }
}
