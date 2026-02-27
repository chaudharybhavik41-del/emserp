<?php

namespace App\Models\Storage;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageFile extends Model
{
    use SoftDeletes;

    protected $table = 'storage_files';

    protected $fillable = [
        'storage_folder_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'checksum',
        'uploaded_by',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'size' => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(StorageFolder::class, 'storage_folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDownloadNameAttribute(): string
    {
        return $this->original_name ?: basename((string) $this->path);
    }
}
