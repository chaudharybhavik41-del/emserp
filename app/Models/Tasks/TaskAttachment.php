<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'task_comment_id',
        'user_id',
        'filename',
        'original_filename',
        'mime_type',
        'file_size',
        'disk',
        'path',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helpers
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function getIconAttribute(): string
    {
        return match(true) {
            $this->isImage() => 'bi-file-image',
            $this->isPdf() => 'bi-file-pdf',
            str_contains($this->mime_type, 'spreadsheet') || str_contains($this->mime_type, 'excel') => 'bi-file-excel',
            str_contains($this->mime_type, 'word') || str_contains($this->mime_type, 'document') => 'bi-file-word',
            str_contains($this->mime_type, 'zip') || str_contains($this->mime_type, 'compressed') => 'bi-file-zip',
            str_contains($this->mime_type, 'text') => 'bi-file-text',
            default => 'bi-file-earmark',
        };
    }

    public function delete(): bool
    {
        // Delete file from storage
        Storage::disk($this->disk)->delete($this->path);

        return parent::delete();
    }
}
