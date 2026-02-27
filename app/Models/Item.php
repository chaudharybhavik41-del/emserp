<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_type_id',
        'material_category_id',
        'material_subcategory_id',
        'uom_id',
        'code',
        'name',
        'short_name',
        'grade',
        'spec',
        'thickness',
        'size',
        'description',
        'density',
        'weight_per_meter',
        'surface_area_per_meter',
        'expense_account_id',
        'asset_account_id',
        'inventory_account_id',
        'is_active',
        'brands',
        'hsn_code',
        'gst_rate_percent',
        'accounting_usage_override',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'density'          => 'float',
        'weight_per_meter' => 'float',
        'surface_area_per_meter' => 'float',
        'brands'           => 'array',
        'gst_rate_percent' => 'float',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(MaterialType::class, 'material_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(MaterialSubcategory::class, 'material_subcategory_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function gstTaxRates(): HasMany
    {
        return $this->hasMany(GstTaxRate::class);
    }

    /**
     * Accessor used by GST logic to get the current IGST rate.
     */
    public function getCurrentGstRatePercentAttribute(): ?float
    {
        if ($this->gst_rate_percent !== null) {
            return (float) $this->gst_rate_percent;
        }

        $rate = $this->gstTaxRates()
            ->orderByDesc('effective_from')
            ->first();

        return $rate ? (float) $rate->igst_rate : null;
    }

    /**
     * Generate next item code for given category/subcategory.
     *
     * New pattern (no year): CAT-SUB-0001
     * - CAT = material category code
     * - SUB = material subcategory code (if present)
     * - 0001 = 4-digit running sequence per CAT+SUB (or per subcategory if it already
     *   includes the category prefix, e.g. PM-001).
     *
     * Works with older codes of form CAT-SUB-YYYY-NNNN by
     * reusing the numeric suffix (NNNN) and ignoring the year.
     */
    public static function generateCodeForTaxonomy(int $materialCategoryId, ?int $materialSubcategoryId = null): string
    {
        $category = MaterialCategory::findOrFail($materialCategoryId);
        $categoryCode = strtoupper(trim($category->code ?? ''));

        $subcategoryCode = null;

        if ($materialSubcategoryId) {
            $subcategory = MaterialSubcategory::find($materialSubcategoryId);

            if ($subcategory && $subcategory->code) {
                $subcategoryCode = strtoupper(trim($subcategory->code));
            }
        }

        if ($subcategoryCode) {
            // Avoid repeating the category prefix when subcategory code already starts with it
            if (strpos($subcategoryCode, $categoryCode . '-') === 0) {
                $basePrefix = $subcategoryCode;
            } else {
                $basePrefix = $categoryCode . '-' . $subcategoryCode;
            }
        } else {
            $basePrefix = $categoryCode;
        }

        // Look at all existing codes with this prefix
        $codes = static::where('code', 'like', $basePrefix . '-%')
            ->pluck('code');

        $maxSeq = 0;

        foreach ($codes as $code) {
            // Numeric part is everything after the last "-"
            $lastDash = strrpos($code, '-');

            if ($lastDash === false) {
                continue;
            }

            $suffix = substr($code, $lastDash + 1);

            if ($suffix !== '' && ctype_digit($suffix)) {
                $num = (int) $suffix;

                if ($num > $maxSeq) {
                    $maxSeq = $num;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        $seq = str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        return $basePrefix . '-' . $seq;
    }

    

}



