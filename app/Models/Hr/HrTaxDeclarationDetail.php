<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTaxDeclarationDetail extends Model
{
    protected $table = 'hr_tax_declaration_details';

    protected $fillable = [
        'hr_tax_declaration_id',
        'section_code',
        'section_name',
        'investment_type',
        'description',
        'declared_amount',
        'max_limit',
        'verified_amount',
        'proof_document_path',
        'proof_submitted',
        'proof_verified',
    ];

    protected $casts = [
        'declared_amount' => 'decimal:2',
        'max_limit' => 'decimal:2',
        'verified_amount' => 'decimal:2',
        'proof_submitted' => 'boolean',
        'proof_verified' => 'boolean',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(HrTaxDeclaration::class, 'hr_tax_declaration_id');
    }
}
