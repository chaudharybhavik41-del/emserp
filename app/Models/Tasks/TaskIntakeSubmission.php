<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskIntakeSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_intake_form_id',
        'task_id',
        'submitted_by',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(TaskIntakeForm::class, 'task_intake_form_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
