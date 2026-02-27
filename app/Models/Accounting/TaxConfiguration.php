<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_master_id',
        'output_account_id',
        'input_account_id',
        'applies_to_document_types',
        'is_default',
    ];

    protected $casts = [
        'applies_to_document_types' => 'array',
        'is_default'                => 'boolean',
    ];

    public function tax(): BelongsTo
    {
        return $this->belongsTo(TaxMaster::class, 'tax_master_id');
    }

    public function outputAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'output_account_id');
    }

    public function inputAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'input_account_id');
    }
}
