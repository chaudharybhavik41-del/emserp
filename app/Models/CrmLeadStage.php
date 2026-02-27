<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLeadStage extends Model
{
    use HasFactory;

    protected $table = 'crm_lead_stages';

    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_won',
        'is_lost',
        'is_closed',
        'is_active',
    ];

    protected $casts = [
        'is_won'    => 'boolean',
        'is_lost'   => 'boolean',
        'is_closed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'lead_stage_id');
    }
}
