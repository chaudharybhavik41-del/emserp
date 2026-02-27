<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GstTaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'effective_from',
        'effective_to',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'cgst_rate'      => 'float',
        'sgst_rate'      => 'float',
        'igst_rate'      => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
