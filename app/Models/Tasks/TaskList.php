<?php

namespace App\Models\Tasks;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class TaskList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'description',
        'color',
        'icon',
        'project_id',
        'default_status_id',
        'default_priority_id',
        'default_assignee_id',
        'visibility',
        'owner_id',
        'sort_order',
        'is_active',
        'is_archived',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskList $list) {
            if (empty($list->code)) {
                $list->code = static::generateCode();
            }
            if (auth()->check()) {
                $list->created_by = $list->created_by ?? auth()->id();
                $list->updated_by = auth()->id();
            }
        });

        static::updating(function (TaskList $list) {
            if (auth()->check()) {
                $list->updated_by = auth()->id();
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TaskList::class, 'parent_id')->orderBy('sort_order');
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function defaultStatus(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'default_status_id');
    }

    public function defaultPriority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'default_priority_id');
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function rootTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_list_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function savedFilters(): HasMany
    {
        return $this->hasMany(TaskSavedFilter::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? 1);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeRootLists($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
              ->orWhere('owner_id', $user->id)
              ->orWhereHas('members', function ($m) use ($user) {
                  $m->where('user_id', $user->id);
              });
        });
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Helpers
    public static function generateCode(): string
    {
        $year = date('Y');
        $prefix = "LST-{$year}-";
        
        $lastCode = static::where('code', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(code, -4) AS UNSIGNED) DESC')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function getTaskCountAttribute(): int
    {
        if (array_key_exists('tasks_count', $this->attributes)) {
            return (int) $this->attributes['tasks_count'];
        }

        return $this->tasks()->count();
    }

    public function getOpenTaskCountAttribute(): int
    {
        if (array_key_exists('open_tasks_count', $this->attributes)) {
            return (int) $this->attributes['open_tasks_count'];
        }

        return $this->tasks()
            ->whereHas('status', fn($q) => $q->where('is_closed', false))
            ->count();
    }

    public function getCompletedTaskCountAttribute(): int
    {
        if (array_key_exists('completed_tasks_count', $this->attributes)) {
            return (int) $this->attributes['completed_tasks_count'];
        }

        return $this->tasks()
            ->whereHas('status', fn($q) => $q->where('is_closed', true))
            ->count();
    }

    public function getProgressPercentAttribute(): int
    {
        $total = $this->task_count;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->completed_task_count / $total) * 100);
    }

    public function getOverdueTaskCountAttribute(): int
    {
        return $this->tasks()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->whereHas('status', fn($q) => $q->where('is_closed', false))
            ->count();
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function getMemberRole(User $user): ?string
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->value('role');
    }

    public function canView(User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        if ($this->owner_id === $user->id) {
            return true;
        }

        return $this->isMember($user);
    }

    public function canEdit(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        $role = $this->getMemberRole($user);
        return in_array($role, ['owner', 'admin']);
    }

    public function getFullPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' / ');
    }
}
