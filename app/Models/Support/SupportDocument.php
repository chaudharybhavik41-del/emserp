<?php

namespace App\Models\Support;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupportDocument extends Model
{
    use HasFactory;

    protected $table = 'support_documents';

    protected $guarded = [];

    protected $casts = [
        'tags'      => 'array',
        'is_active' => 'boolean',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SupportFolder::class, 'support_folder_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
            ->orderByDesc('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
