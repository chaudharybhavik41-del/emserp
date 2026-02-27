<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id',
        'branch_name',
        'gstin',
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
    ];

    protected static function booted(): void
    {
        static::saving(function (self $branch) {
            if ($branch->gstin) {
                $branch->gstin = strtoupper(preg_replace('/\s+/', '', $branch->gstin));
            }
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
