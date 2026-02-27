<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Item;
use App\Models\MaterialSubcategory;
use App\Models\MaterialType;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * ItemAccountingResolver
 *
 * Resolves which ledger account should be debited when posting an item.
 *
 * IMPORTANT:
 * - PurchaseBillPostingService (and other posting services) expect the legacy
 *   method resolvePurchaseAccountId(). Some recent patches introduced a resolver
 *   without that method, which caused runtime errors.
 *
 * This version is backward compatible and also supports Phase-C (tool_stock)
 * and fixed-asset safety (do not silently post fixed assets into inventory).
 */
class ItemAccountingResolver
{
    /**
     * Backward-compatible helper used by posting services.
     * Returns the debit account id for purchase postings.
     */
    public function resolvePurchaseAccountId(?Item $item, ?string $usageOverride = null): ?int
    {
        if (! $item) {
            return null;
        }

        $acc = $this->resolveDebitAccountForItem(
            $item,
            $item->relationLoaded('type') ? $item->getRelation('type') : $item->type,
            $item->relationLoaded('subcategory') ? $item->getRelation('subcategory') : $item->subcategory,
            $usageOverride
        );

        return $acc?->id;
    }

    /**
     * Resolve the correct debit Account for an item.
     *
     * Supported call patterns:
     *  - resolveDebitAccountForItem($item)
     *  - resolveDebitAccountForItem($item, $usageOverride)                (legacy style)
     *  - resolveDebitAccountForItem($item, $type, $subcategory, $override) (new style)
     */
    public function resolveDebitAccountForItem(Item $item, $type = null, $subcategory = null, $usageOverride = null): Account
    {
        // Legacy-style call: second argument is actually usageOverride
        if (is_string($type) && $subcategory === null && $usageOverride === null) {
            $usageOverride = $type;
            $type = null;
            $subcategory = null;
        }

        if (! ($type instanceof MaterialType)) {
            $type = $item->relationLoaded('type') ? $item->getRelation('type') : $item->type;
        }

        if (! ($subcategory instanceof MaterialSubcategory)) {
            $subcategory = $item->relationLoaded('subcategory') ? $item->getRelation('subcategory') : $item->subcategory;
        }

        // Priority:
        // 1) Item-level override
        // 2) Explicit override passed by caller
        // 3) Material type default
        $usage = (string) (
            $item->accounting_usage_override
            ?: ($usageOverride ?: ($type?->accounting_usage ?? 'inventory'))
        );

        $usage = strtolower(trim($usage));

        switch ($usage) {
            case 'expense':
                return $this->resolveExpenseAccount($item, $subcategory);

            case 'fixed_asset':
                return $this->resolveAssetAccount($item, $subcategory);

            case 'tool_stock':
                return $this->resolveToolInventoryAccount($item, $subcategory);

            case 'mixed':
                // Default for mixed: treat as inventory unless explicitly mapped
                return $this->resolveInventoryAccount($item, $subcategory, $type);

            case 'inventory':
            default:
                return $this->resolveInventoryAccount($item, $subcategory, $type);
        }
    }

    protected function resolveExpenseAccount(Item $item, ?MaterialSubcategory $subcategory): Account
    {
        if ($item->expenseAccount) {
            return $item->expenseAccount;
        }

        if ($subcategory && $subcategory->expenseAccount) {
            return $subcategory->expenseAccount;
        }

        $code = Config::get('accounting.default_accounts.consumables_expense_code');
        $acc  = $this->findFallbackAccountByCode($code);

        if (! $acc) {
            throw new RuntimeException(
                'Expense ledger not found. Configure accounting.default_accounts.consumables_expense_code '
                . 'or set expense_account_id on item/subcategory.'
            );
        }

        return $acc;
    }

    protected function resolveInventoryAccount(Item $item, ?MaterialSubcategory $subcategory, ?MaterialType $type): Account
    {
        if ($item->inventoryAccount) {
            return $item->inventoryAccount;
        }

        if ($subcategory && $subcategory->inventoryAccount) {
            return $subcategory->inventoryAccount;
        }

        // Consumables should go to Inventory - Consumables (not Raw Material)
        // when there is no explicit mapping.
        $typeCode = strtoupper(trim((string) ($type?->code ?? '')));
        if (in_array($typeCode, ['CONSUMABLE', 'CONSUMABLES'], true)) {
            $consCode = Config::get('accounting.store.inventory_consumables_account_code');
            $consAcc  = $this->findFallbackAccountByCode($consCode);
            if ($consAcc) {
                return $consAcc;
            }
        }

        $code = Config::get('accounting.default_accounts.inventory_raw_material_code') ?: 'INV-RM';
        $acc  = $this->findFallbackAccountByCode($code);

        if (! $acc) {
            throw new RuntimeException('Inventory ledger not found for code: ' . $code);
        }

        return $acc;
    }

    /**
     * Tool stock behaves like inventory, but is posted to a dedicated inventory ledger (INV-TOOLS).
     */
    protected function resolveToolInventoryAccount(Item $item, ?MaterialSubcategory $subcategory): Account
    {
        // Allow explicit inventory mapping to win
        if ($item->inventoryAccount) {
            return $item->inventoryAccount;
        }

        if ($subcategory && $subcategory->inventoryAccount) {
            return $subcategory->inventoryAccount;
        }

        $code = Config::get('accounting.default_accounts.inventory_tools_code') ?: 'INV-TOOLS';
        $acc  = $this->findFallbackAccountByCode($code);

        if (! $acc) {
            throw new RuntimeException('Tools inventory ledger not found for code: ' . $code . ' (expected INV-TOOLS).');
        }

        return $acc;
    }

    /**
     * Fixed asset postings.
     */
    protected function resolveAssetAccount(Item $item, ?MaterialSubcategory $subcategory): Account
    {
        if ($item->assetAccount) {
            return $item->assetAccount;
        }

        if ($subcategory && $subcategory->assetAccount) {
            return $subcategory->assetAccount;
        }

        $code =
            Config::get('accounting.default_accounts.fixed_asset_machinery_code')
            ?: Config::get('accounting.default_accounts.fixed_asset_default_code')
            ?: 'FA-MACHINERY';

        $acc = $this->findFallbackAccountByCode($code);

        if (! $acc) {
            // SAFETY: do not silently post fixed-asset purchases into inventory.
            throw new RuntimeException(
                'Fixed asset ledger not found for code: ' . $code . '. '
                . 'Set Item.asset_account_id or MaterialSubcategory.asset_account_id, '
                . 'or configure accounting.default_accounts.fixed_asset_machinery_code (e.g. FA-MACHINERY).'
            );
        }

        return $acc;
    }

    protected function findFallbackAccountByCode(?string $code): ?Account
    {
        if (! $code) {
            return null;
        }

        return Account::where('code', $code)->first();
    }
}
