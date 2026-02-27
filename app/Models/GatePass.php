<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GatePass extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'gatepass_date' => 'date',
        'gatepass_time' => 'datetime:H:i',
        'is_returnable' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function toParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'to_party_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GatePassLine::class);
    }

    public function storeReturns(): HasMany
    {
        return $this->hasMany(StoreReturn::class, 'gate_pass_id');
    }


    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'project_material'      => 'Project Material',
            'machinery_maintenance' => 'Machinery Maintenance',
            default                 => ucfirst(str_replace('_', ' ', (string) $this->type)),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'              => 'Draft',
            'out'                => 'Out',
            'partially_returned' => 'Partially Returned',
            'closed'             => 'Closed',
            'cancelled'          => 'Cancelled',
            default              => ucfirst((string) $this->status),
        };
    }
}