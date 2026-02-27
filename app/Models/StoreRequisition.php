<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreRequisition extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'requisition_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StoreRequisitionLine::class);
    }


    /**
     * Helper to check if this requisition is fully issued
     * (all lines have issued_qty >= required_qty).
     */
    public function isFullyIssued(): bool
    {
        $this->loadMissing('lines');

        if ($this->lines->isEmpty()) {
            return false;
        }

        foreach ($this->lines as $line) {
            $required = (float) ($line->required_qty ?? 0);
            $issued   = (float) ($line->issued_qty ?? 0);

            if ($required > $issued + 0.0001) {
                return false;
            }
        }

        return true;
    }

    public function getIsFullyIssuedAttribute(): bool
    {
        return $this->isFullyIssued();
    }
}


