<?php

namespace App\Models\Tasks;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'icon',
        'level',
        'is_default',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskPriority $priority) {
            if (empty($priority->slug)) {
                $priority->slug = Str::slug($priority->name);
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
        return $this->hasMany(Task::class, 'priority_id');
    }

    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'default_priority_id');
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

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('level')->orderBy('sort_order');
    }

    // Helpers
    public function getColorStyleAttribute(): string
    {
        return "background-color: {$this->color}";
    }

    public function getBadgeClassAttribute(): string
    {
        return match($this->slug) {
            'urgent', 'critical' => 'bg-danger',
            'high' => 'bg-warning text-dark',
            'medium', 'normal' => 'bg-primary',
            'low' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    // Default priorities factory
    public static function getDefaultPriorities(): array
    {
        return [
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#dc2626', 'icon' => 'bi-exclamation-triangle-fill', 'level' => 4, 'sort_order' => 1],
            ['name' => 'High', 'slug' => 'high', 'color' => '#f97316', 'icon' => 'bi-arrow-up-circle-fill', 'level' => 3, 'sort_order' => 2],
            ['name' => 'Medium', 'slug' => 'medium', 'color' => '#eab308', 'icon' => 'bi-dash-circle-fill', 'level' => 2, 'is_default' => true, 'sort_order' => 3],
            ['name' => 'Low', 'slug' => 'low', 'color' => '#22c55e', 'icon' => 'bi-arrow-down-circle-fill', 'level' => 1, 'sort_order' => 4],
            ['name' => 'None', 'slug' => 'none', 'color' => '#6b7280', 'icon' => 'bi-circle', 'level' => 0, 'sort_order' => 5],
        ];
    }
}
