<?php

namespace App\Models\Storage;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageFolderUserAccess extends Model
{
    protected $table = 'storage_folder_users';

    protected $fillable = [
        'storage_folder_id',
        'user_id',
        'can_view',
        'can_upload',
        'can_download',
        'can_edit',
        'can_delete',
        'can_manage_access',
        'created_by',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_upload' => 'boolean',
        'can_download' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_manage_access' => 'boolean',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(StorageFolder::class, 'storage_folder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
