<?php

namespace App\Models\Storage;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageFolder extends Model
{
    use SoftDeletes;

    protected $table = 'storage_folders';

    protected $fillable = [
        'parent_id',
        'project_id',
        'name',
        'description',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StorageFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(StorageFolder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(StorageFile::class, 'storage_folder_id');
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(StorageFolderUserAccess::class, 'storage_folder_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function accessForUser(int $userId): ?StorageFolderUserAccess
    {
        return $this->accesses()->where('user_id', $userId)->first();
    }

    public function scopeViewableBy($query, User $user)
    {
        return $query->whereHas('accesses', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('can_view', true);
        });
    }
}
