<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractorWorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'subcontractor_id',
        'project_id',
        'work_order_number',
        'work_order_date',
        'start_date',
        'end_date',
        'payment_terms_days',
        'retention_percent',
        'security_deposit_percent',
        'other_terms',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'work_order_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'payment_terms_days' => 'integer',
        'retention_percent' => 'float',
        'security_deposit_percent' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'subcontractor_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function raBills(): HasMany
    {
        return $this->hasMany(SubcontractorRaBill::class, 'work_order_id');
    }

    public static function generateNextNumber(?int $companyId = null): string
    {
        $companyId ??= (int) config('accounting.default_company_id', 1);
        $prefix = 'SCWO-' . now()->format('ym') . '-';

        $lastNumber = static::query()
            ->where('company_id', $companyId)
            ->where('work_order_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('work_order_number');

        $sequence = 1;
        if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
