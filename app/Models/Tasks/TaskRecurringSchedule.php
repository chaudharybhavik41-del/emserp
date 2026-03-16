<?php

namespace App\Models\Tasks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRecurringSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'task_list_id',
        'template_id',
        'name',
        'title_template',
        'description_template',
        'status_id',
        'priority_id',
        'assignee_id',
        'task_type',
        'estimated_minutes',
        'default_labels',
        'interval_unit',
        'interval_value',
        'next_run_at',
        'last_run_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'default_labels' => 'array',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
        'estimated_minutes' => 'integer',
        'interval_value' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'template_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'priority_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
