<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'asset_type',
        'asset_code',
        'name',
        'item_id',
        'machine_type',
        'serial_no',
        'make',
        'model',
        'capacity',
        'project_id',
        'location_id',
        'vendor_party_id',
        'purchase_date',
        'put_to_use_date',
        'opening_wdv',
        'opening_as_of',
        'original_cost',
        'accum_dep_opening',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'put_to_use_date' => 'date',
        'opening_as_of' => 'date',
        'opening_wdv' => 'decimal:2',
        'original_cost' => 'decimal:2',
        'accum_dep_opening' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function links(): HasMany
    {
        return $this->hasMany(FixedAssetLink::class);
    }

    public static function generateMachineryCode(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');
        $prefix = 'MCH-' . $year . '-';

        $lastCode = static::query()
            ->where('asset_type', 'machinery')
            ->where('asset_code', 'like', $prefix . '%')
            ->orderByDesc('asset_code')
            ->value('asset_code');

        $next = 1;
        if ($lastCode && preg_match('/^MCH-\d{4}-(\d{4})$/', $lastCode, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

