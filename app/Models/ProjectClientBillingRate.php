<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectClientBillingRate extends Model
{
    use HasFactory;

    public const LINE_TYPE_GENERIC = 'generic';
    public const LINE_TYPE_ASSEMBLY_CODE = 'assembly_code';
    public const LINE_TYPE_BOQ_ITEM_CODE = 'boq_item_code';
    public const LINE_TYPE_SCRAP = 'scrap';

    protected $fillable = [
        'project_id',
        'line_type',
        'source_key',
        'description',
        'uom_id',
        'rate',
        'revenue_account_id',
        'sac_hsn_code',
        'effective_from',
        'effective_to',
        'is_active',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate' => 'float',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public static function lineTypeOptions(): array
    {
        return [
            self::LINE_TYPE_GENERIC => 'Generic Default',
            self::LINE_TYPE_ASSEMBLY_CODE => 'Assembly Code',
            self::LINE_TYPE_BOQ_ITEM_CODE => 'BOQ / Milestone Code',
            self::LINE_TYPE_SCRAP => 'Scrap Sale',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function getLineTypeLabelAttribute(): string
    {
        return static::lineTypeOptions()[$this->line_type] ?? ucfirst(str_replace('_', ' ', (string) $this->line_type));
    }
}
