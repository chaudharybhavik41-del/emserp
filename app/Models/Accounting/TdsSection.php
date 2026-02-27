<?php

namespace App\Models\Accounting;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsSection extends Model
{
    use HasFactory;

    protected $table = 'tds_sections';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'default_rate',
        'is_active',
    ];

    protected $casts = [
        'default_rate' => 'float',
        'is_active'    => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $rate = number_format((float) $this->default_rate, 4);
        return trim($this->code . ' - ' . $this->name . ' (' . rtrim(rtrim($rate, '0'), '.') . '%)');
    }
}
