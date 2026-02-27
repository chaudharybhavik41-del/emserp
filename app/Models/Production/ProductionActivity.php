<?php

namespace App\Models\Production;

use App\Models\Uom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionActivity extends Model
{
    use HasFactory;

    protected $table = 'production_activities';

    protected $fillable = [
        'code',
        'name',
        'applies_to',
        'default_sequence',
        'billing_uom_id',
        'calculation_method',
        'is_fitupp',
        'requires_machine',
        'requires_qc',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_sequence' => 'integer',
        'billing_uom_id' => 'integer',
        'is_fitupp' => 'boolean',
        'requires_machine' => 'boolean',
        'requires_qc' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function appliesToOptions(): array
    {
        return [
            'part' => 'Part',
            'assembly' => 'Assembly',
            'both' => 'Both',
        ];
    }

    public static function calculationMethodOptions(): array
    {
        return [
            'manual' => 'Manual Entry',
            'kg_from_weight' => 'Kg from planned weight',
            'meter_from_len' => 'Meter from planned length',
            'sqm_from_area' => 'Sqm from planned area',
            'nos' => 'Nos (count)',
        ];
    }

    public function billingUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'billing_uom_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
