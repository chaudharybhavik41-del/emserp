<?php

namespace App\Services\Machinery;

use App\Models\Machine;
use App\Models\PurchaseBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-register machines from a posted Purchase Bill.
 *
 * Design goals:
 * - Idempotent (safe to call multiple times; will only create missing machines).
 * - Non-blocking for accounting posting (caller should wrap in try/catch).
 * - Uses Item classification (material type = MACHINERY) to decide eligibility.
 */
class MachineAutoRegistrationService
{
    public function registerFromPurchaseBill(PurchaseBill $bill): int
    {
        // Guard: feature depends on linkage columns added in Phase A
        if (!Schema::hasTable('machines')) {
            return 0;
        }

        if (!Schema::hasColumn('machines', 'purchase_bill_id') || !Schema::hasColumn('machines', 'purchase_bill_line_id')) {
            return 0;
        }

        // Only posted bills should auto-register
        if (($bill->status ?? null) !== 'posted') {
            return 0;
        }

        $bill->loadMissing('lines.item.type', 'supplier');

        if ($bill->lines->isEmpty()) {
            return 0;
        }

        $machineryTypeId = (int) (DB::table('material_types')->where('code', 'MACHINERY')->value('id') ?? 0);
        if ($machineryTypeId <= 0) {
            return 0;
        }

        $defaultCategoryId = (int) (DB::table('material_categories')
            ->where('material_type_id', $machineryTypeId)
            ->where('code', 'OTHER')
            ->value('id') ?? 0);

        $created = 0;

        foreach ($bill->lines as $line) {
            $item = $line->item;
            if (!$item) {
                continue;
            }

            // Eligible if item material type is MACHINERY
            $itemType = $item->type;
            $itemTypeId = (int) (($item->material_type_id ?? null) ?: ($itemType->id ?? 0));
            if ($itemTypeId !== $machineryTypeId) {
                continue;
            }

            $qty = (float) ($line->qty ?? $line->quantity ?? 0);
            $qtyInt = (int) round($qty);
            if ($qtyInt <= 0) {
                $qtyInt = 1;
            }

            $existing = (int) Machine::query()
                ->where('purchase_bill_line_id', (int) $line->id)
                ->count();

            $missing = $qtyInt - $existing;
            if ($missing <= 0) {
                continue;
            }

            $materialCategoryId = (int) (($item->material_category_id ?? null) ?: ($defaultCategoryId ?: 0));
            if ($materialCategoryId <= 0) {
                // Cannot create without a category (FK)
                continue;
            }

            $materialSubcategoryId = (int) ($item->material_subcategory_id ?? 0);
            if ($materialSubcategoryId <= 0) {
                $materialSubcategoryId = null;
            }

            $baseAmount = (float) (($line->basic_amount ?? null) ?: ($line->taxable_amount ?? null) ?: 0);
            $unitCost = $qtyInt > 0 ? ($baseAmount / $qtyInt) : $baseAmount;
            $unitCost = round($unitCost, 2);

            // Determine accounting treatment (fixed_asset vs tool_stock)
            $treat = (string) (($item->accounting_usage_override ?? null) ?: ($itemType->accounting_usage ?? 'fixed_asset'));
            if (!in_array($treat, ['fixed_asset', 'tool_stock'], true)) {
                $treat = 'fixed_asset';
            }

            for ($i = 0; $i < $missing; $i++) {
                $code = method_exists(Machine::class, 'generateCode')
                    ? Machine::generateCode($materialCategoryId)
                    : ('MACH-' . date('y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));

                Machine::create([
                    'material_type_id'        => $machineryTypeId,
                    'material_category_id'    => $materialCategoryId,
                    'material_subcategory_id' => $materialSubcategoryId,

                    'code'          => $code,
                    'name'          => (string) ($item->name ?? 'MACHINERY'),
                    'serial_number' => $code,

                    // Purchase linkage (Phase A)
                    'purchase_bill_id'      => (int) $bill->id,
                    'purchase_bill_line_id' => (int) $line->id,

                    // Purchase details
                    'supplier_party_id'    => (int) ($bill->supplier_id ?? 0),
                    'purchase_date'        => $bill->bill_date,
                    'purchase_price'       => $unitCost,
                    'purchase_invoice_no'  => $bill->bill_number,

                    // Accounting classification
                    'accounting_treatment' => $treat,

                    // Default ops status
                    'status' => 'active',
                ]);

                $created++;
            }
        }

        return $created;
    }
}
