<?php

namespace App\Models\Tasks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'task_list_id',
        'name',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
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
