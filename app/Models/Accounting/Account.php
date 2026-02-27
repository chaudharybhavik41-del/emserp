<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_group_id',
        'name',
        'code',
        'type',
        'is_active',
        'opening_balance',
        'opening_balance_type',
        'opening_balance_date',
        'gstin',
        'pan',
        'credit_limit',
        'credit_days',
        'related_model_type',
        'related_model_id',
        'is_gst_applicable',
        'is_system',
        'system_key',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'opening_balance'      => 'decimal:2',
        'credit_limit'         => 'decimal:2',
        'is_gst_applicable'    => 'boolean',
        'opening_balance_date' => 'date',
        'is_system'            => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function relatedModel(): MorphTo
    {
        return $this->morphTo('related_model');
    }
}
