<?php

namespace App\Models\Tasks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskIntakeForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'task_list_id',
        'name',
        'slug',
        'description',
        'fields',
        'default_status_id',
        'default_priority_id',
        'default_assignee_id',
        'success_message',
        'is_active',
        'requires_auth',
        'created_by',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_active' => 'boolean',
        'requires_auth' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskIntakeForm $form) {
            if (blank($form->slug)) {
                $form->slug = Str::slug($form->name);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'default_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'default_priority_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TaskIntakeSubmission::class);
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? 1);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
