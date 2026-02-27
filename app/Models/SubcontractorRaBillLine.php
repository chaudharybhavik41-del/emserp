<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DEV-3: Subcontractor RA Bill Line Model
 * 
 * BOQ-wise breakdown of work done for the RA Bill
 */
class SubcontractorRaBillLine extends Model
{
    use HasFactory;

    protected $table = 'subcontractor_ra_bill_lines';

    protected $fillable = [
        'subcontractor_ra_bill_id',
        'line_no',
        'boq_item_id',
        'boq_item_code',
        'description',
        'uom_id',
        'contracted_qty',
        'previous_qty',
        'current_qty',
        'cumulative_qty',
        'rate',
        'previous_amount',
        'current_amount',
        'cumulative_amount',
        'remarks',
    ];

    protected $casts = [
        'contracted_qty'    => 'float',
        'previous_qty'      => 'float',
        'current_qty'       => 'float',
        'cumulative_qty'    => 'float',
        'rate'              => 'float',
        'previous_amount'   => 'float',
        'current_amount'    => 'float',
        'cumulative_amount' => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function raBill(): BelongsTo
    {
        return $this->belongsTo(SubcontractorRaBill::class, 'subcontractor_ra_bill_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate amounts based on quantities and rate
     */
    public function calculateAmounts(): void
    {
        $this->cumulative_qty = $this->previous_qty + $this->current_qty;
        $this->previous_amount = $this->previous_qty * $this->rate;
        $this->current_amount = $this->current_qty * $this->rate;
        $this->cumulative_amount = $this->cumulative_qty * $this->rate;
    }

    /**
     * Get balance quantity (contracted - cumulative)
     */
    public function getBalanceQtyAttribute(): float
    {
        return $this->contracted_qty - $this->cumulative_qty;
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentAttribute(): float
    {
        if ($this->contracted_qty <= 0) {
            return 0;
        }
        return round(($this->cumulative_qty / $this->contracted_qty) * 100, 2);
    }
}
