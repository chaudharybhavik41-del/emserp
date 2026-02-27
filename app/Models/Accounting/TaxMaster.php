<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tax_type',
        'tax_rate',
        'applicable_on',
        'is_input_allowed',
        'is_reverse_charge',
        'is_active',
    ];

    protected $casts = [
        'tax_rate'        => 'decimal:2',
        'is_input_allowed'=> 'boolean',
        'is_reverse_charge'=> 'boolean',
        'is_active'       => 'boolean',
    ];

    public function configurations(): HasMany
    {
        return $this->hasMany(TaxConfiguration::class);
    }
}
