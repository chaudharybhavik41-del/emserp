<?php

namespace App\Services;

use App\Models\Item;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;

/**
 * Generates item codes based on category + subcategory prefix.
 *
 * New pattern (no year):
 *   CAT-PFX-0001
 *   e.g. CS-ARC-0001, RAW-PL-0001
 *
 * - CAT: material_categories.code (user defined prefix)
 * - PFX: subcategory item_code_prefix (or subcategory code if empty)
 * - SEQ: 4 digit running number per CAT+PFX combination
 *
 * Old codes like CAT-SUB-2025-0001 remain untouched; we only look at
 * codes matching the new pattern when incrementing.
 */
class ItemCodeGenerator
{
    public function generate(MaterialCategory $category, ?MaterialSubcategory $subcategory = null): string
    {
        $parts = [];

        $catCode = strtoupper($category->code);
        $parts[] = $catCode;

        if ($subcategory) {
            $prefix = $subcategory->getItemCodePrefix();

            if (! $prefix) {
                // Fallback to subcategory code if prefix not set
                $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($subcategory->code ?? ''));
                $prefix = mb_substr($prefix, 0, 5);
            }

            if ($prefix) {
                $parts[] = $prefix;
            }
        }

        $basePrefix = implode('-', $parts);     // e.g. CS-ARC
        $searchPrefix = $basePrefix . '-';      // e.g. CS-ARC-

        // Fetch all existing codes starting with our prefix
        $existingCodes = Item::where('code', 'like', $searchPrefix . '%')
            ->pluck('code');

        $baseSegments = count($parts);          // e.g. 2 for CS-ARC
        $maxSeq = 0;

        foreach ($existingCodes as $code) {
            $segments = explode('-', $code);

            // New pattern adds exactly one numeric segment after base prefix
            if (count($segments) === $baseSegments + 1) {
                $last = end($segments);
                if (ctype_digit($last)) {
                    $maxSeq = max($maxSeq, (int) $last);
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        return sprintf('%s-%04d', $basePrefix, $nextSeq);
    }
}
