<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployeeAsset extends Model
{
    protected $table = 'hr_employee_assets';

    protected $fillable = [
        'hr_employee_id',
        'asset_type',
        'asset_name',
        'asset_code',
        'serial_number',
        'make',
        'model',
        'issued_date',
        'return_date',
        'asset_value',
        'deposit_amount',
        'status',
        'condition_at_issue',
        'condition_at_return',
        'remarks',
        'issued_by',
        'returned_to',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'return_date' => 'date',
        'asset_value' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'hr_employee_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function returnedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }
}
