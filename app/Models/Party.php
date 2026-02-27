<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Party extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_supplier',
        'is_contractor',
        'is_client',
        'code',
        'name',
        'legal_name',
        'gstin',
        'pan',
        'msme_no',
        'primary_phone',
        'primary_email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'country',
        'gst_legal_name',
        'gst_trade_name',
        'gst_status',
        'gst_state_code',
        'gst_raw_json',
        'is_active',
    ];

    protected $casts = [
        'is_supplier'   => 'boolean',
        'is_contractor' => 'boolean',
        'is_client'     => 'boolean',
        'is_active'     => 'boolean',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(PartyBank::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(PartyBranch::class)->orderBy('gstin');
    }

    public function primaryContact(): HasMany
    {
        return $this->contacts()->where('is_primary', true);
    }

    public function primaryBank(): HasMany
    {
        return $this->banks()->where('is_primary', true);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}


