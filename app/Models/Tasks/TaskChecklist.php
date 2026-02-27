<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order');
    }

    // Helpers
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getCompletedItemsAttribute(): int
    {
        return $this->items()->where('is_completed', true)->count();
    }

    public function getProgressPercentAttribute(): int
    {
        $total = $this->total_items;
        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->completed_items / $total) * 100);
    }

    public function isComplete(): bool
    {
        return $this->total_items > 0 && $this->completed_items === $this->total_items;
    }
}


// TaskChecklistItem Model
