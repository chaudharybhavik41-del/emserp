<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'parent_id',
        'user_id',
        'content',
        'is_internal',
        'is_pinned',
        'edited_at',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_pinned' => 'boolean',
        'edited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (TaskComment $comment) {
            // Log activity
            $comment->task->logActivity('commented', null, null, null, [
                'comment_id' => $comment->id,
                'preview' => \Str::limit($comment->content, 100),
            ]);

            // Add commenter as watcher if not already
            if (!$comment->task->isWatching($comment->user)) {
                $comment->task->addWatcher($comment->user);
            }
        });
    }

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_id')->orderBy('created_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeRootComments($query)
    {
        return $query->whereNull('parent_id');
    }

    // Helpers
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function canEdit(User $user): bool
    {
        // Can edit own comments within 24 hours
        return $this->user_id === $user->id 
            && $this->created_at->gt(now()->subHours(24));
    }

    public function canDelete(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function markAsEdited(): void
    {
        $this->update(['edited_at' => now()]);
    }

    public function togglePin(): void
    {
        $this->update(['is_pinned' => !$this->is_pinned]);
    }

    public function getFormattedContentAttribute(): string
    {
        // Convert mentions @username to links
        $content = preg_replace(
            '/@(\w+)/',
            '<span class="text-primary fw-medium">@$1</span>',
            e($this->content)
        );

        // Convert line breaks
        return nl2br($content);
    }

    public function getMentionedUsersAttribute(): array
    {
        preg_match_all('/@(\w+)/', $this->content, $matches);
        return $matches[1] ?? [];
    }
}
