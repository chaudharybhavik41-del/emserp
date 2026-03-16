<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriodLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'from_date',
        'to_date',
        'reason',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'is_active' => 'boolean',
    ];
}
