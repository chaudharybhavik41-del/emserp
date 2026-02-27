<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'code',
        'title',
        'party_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'lead_source_id',
        'lead_stage_id',
        'expected_value',
        'probability',
        'lead_date',
        'expected_close_date',
        'owner_id',
        'department_id',
        'status',
        'lost_reason',
        'notes',
    ];

    protected $casts = [
        'expected_value'      => 'decimal:2',
        'probability'         => 'integer',
        'lead_date'           => 'date',
        'expected_close_date' => 'date',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmLeadSource::class, 'lead_source_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStage::class, 'lead_stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmLeadActivity::class, 'lead_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(CrmQuotation::class, 'lead_id');
    }
}
