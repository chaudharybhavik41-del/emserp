<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;

return new class extends Migration
{
    public function up(): void
    {
        // This migration safely inserts / updates system ledgers needed for Tool Stock custody accounting.
        // It does NOT delete anything.

        $companyId = (int) Config::get('accounting.default_company_id', 1);

        if (! class_exists(\App\Models\Accounting\Account::class) || ! class_exists(\App\Models\Accounting\AccountGroup::class)) {
            return;
        }

        /** @var \App\Models\Accounting\AccountGroup|null $inventoryGroup */
        $inventoryGroup = \App\Models\Accounting\AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', 'INVENTORY')
            ->first();

        /** @var \App\Models\Accounting\AccountGroup|null $indirectExpGroup */
        $indirectExpGroup = \App\Models\Accounting\AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', 'INDIRECT_EXPENSES')
            ->first();

        if (! $inventoryGroup || ! $indirectExpGroup) {
            // Chart not yet seeded; skip.
            return;
        }

        // 1) Inventory ledger for short-term tools
        $this->upsertAccount($companyId, $inventoryGroup->id, 'INV-TOOLS', 'Tools Inventory', 'inventory');

        // 2) Custody ledger (tools issued to contractor/worker but still owned by company)
        $this->upsertAccount($companyId, $inventoryGroup->id, 'TOOLS-WITH-CONTRACTOR', 'Tools In Custody (Contractor/Worker)', 'ledger');

        // 3) Scrap / loss expense ledger
        $this->upsertAccount($companyId, $indirectExpGroup->id, 'TOOLS-SCRAP-LOSS', 'Tools Scrap / Loss', 'ledger');
    }

    public function down(): void
    {
        // Intentionally NO down() deletes. Ledgers may have transactions.
    }

    private function upsertAccount(int $companyId, int $groupId, string $code, string $name, string $type): void
    {
        \App\Models\Accounting\Account::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'code'       => $code,
            ],
            [
                'account_group_id' => $groupId,
                'name'             => $name,
                'type'             => $type,
                'is_active'        => true,
            ]
        );
    }
};
