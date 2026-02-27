<?php

namespace App\Models\Tasks;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'icon',
        'category',
        'is_default',
        'is_closed',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_closed' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskStatus $status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'status_id');
    }

    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'default_status_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? 1);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helpers
    public function isOpen(): bool
    {
        return !$this->is_closed;
    }

    public function isClosed(): bool
    {
        return $this->is_closed;
    }

    public function getColorClassAttribute(): string
    {
        // Return Tailwind-compatible color classes
        return match($this->category) {
            'open' => 'bg-gray-100 text-gray-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'review' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Default statuses factory
    public static function getDefaultStatuses(): array
    {
        return [
            ['name' => 'To Do', 'slug' => 'to-do', 'color' => '#6b7280', 'icon' => 'bi-circle', 'category' => 'open', 'is_default' => true, 'sort_order' => 1],
            ['name' => 'In Progress', 'slug' => 'in-progress', 'color' => '#3b82f6', 'icon' => 'bi-play-circle', 'category' => 'in_progress', 'sort_order' => 2],
            ['name' => 'In Review', 'slug' => 'in-review', 'color' => '#f59e0b', 'icon' => 'bi-eye', 'category' => 'review', 'sort_order' => 3],
            ['name' => 'On Hold', 'slug' => 'on-hold', 'color' => '#ef4444', 'icon' => 'bi-pause-circle', 'category' => 'open', 'sort_order' => 4],
            ['name' => 'Completed', 'slug' => 'completed', 'color' => '#10b981', 'icon' => 'bi-check-circle', 'category' => 'completed', 'is_closed' => true, 'sort_order' => 5],
            ['name' => 'Cancelled', 'slug' => 'cancelled', 'color' => '#dc2626', 'icon' => 'bi-x-circle', 'category' => 'cancelled', 'is_closed' => true, 'sort_order' => 6],
        ];
    }
}
