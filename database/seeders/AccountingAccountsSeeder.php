<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * Seeder for Additional Accounting Accounts
 * 
 * Creates accounts required for:
 * - DEV-3: Subcontractor RA Bill Posting
 * - DEV-4: Client RA / Sales Invoice Posting
 * - DEV-9: Project Cost Sheet
 * - DEV-11: GST Output
 */
class AccountingAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Config::get('accounting.default_company_id', 1);

        // Get or create account groups
        $groups = $this->ensureAccountGroups($companyId);

        // Create WIP accounts (for project costing)
        $this->createWipAccounts($companyId, $groups);

        // Create Revenue accounts (for sales posting)
        $this->createRevenueAccounts($companyId, $groups);

        // Create Output GST accounts (for sales posting)
        $this->createOutputGstAccounts($companyId, $groups);

        // Create additional expense accounts
        $this->createExpenseAccounts($companyId, $groups);

        $this->command->info('Accounting accounts seeded successfully.');
    }

    /**
     * Ensure required account groups exist
     */
    protected function ensureAccountGroups(int $companyId): array
    {
        $groups = [];

        // Work In Progress group (under Assets)
        $assetsGroup = AccountGroup::where('company_id', $companyId)
            ->where('code', 'ASSETS')
            ->first();

        if ($assetsGroup) {
            $groups['wip'] = AccountGroup::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'WORK_IN_PROGRESS'],
                [
                    'name'       => 'Work In Progress',
                    'parent_id'  => $assetsGroup->id,
                    'nature'     => 'asset',
                    'is_primary' => false,
                    'sort_order' => 145,
                ]
            );
        }

        // Revenue group (under Income)
        $incomeGroup = AccountGroup::where('company_id', $companyId)
            ->where('code', 'INCOME')
            ->first();

        if ($incomeGroup) {
            $groups['revenue'] = AccountGroup::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'REVENUE'],
                [
                    'name'       => 'Revenue',
                    'parent_id'  => $incomeGroup->id,
                    'nature'     => 'income',
                    'is_primary' => false,
                    'sort_order' => 305,
                ]
            );
        }

        // GST Output group (under Duties & Taxes in Liabilities)
        $dutiesTaxesGroup = AccountGroup::where('company_id', $companyId)
            ->where('code', 'DUTIES_TAXES')
            ->first();

        if ($dutiesTaxesGroup) {
            $groups['gst_output'] = AccountGroup::firstOrCreate(
                ['company_id' => $companyId, 'code' => 'GST_OUTPUT_GROUP'],
                [
                    'name'       => 'GST Output',
                    'parent_id'  => $dutiesTaxesGroup->id,
                    'nature'     => 'liability',
                    'is_primary' => false,
                    'sort_order' => 225,
                ]
            );
        }

        // Get existing groups
        $groups['gst_input'] = AccountGroup::where('company_id', $companyId)
            ->where('code', 'GST_INPUT_GROUP')
            ->first();

        $groups['expenses'] = AccountGroup::where('company_id', $companyId)
            ->where('code', 'DIRECT_EXPENSES')
            ->first();

        $groups['creditors'] = AccountGroup::where('company_id', $companyId)
            ->where('code', 'SUNDRY_CREDITORS')
            ->first();

        return $groups;
    }

    /**
     * Create Work In Progress accounts for project costing
     */
    protected function createWipAccounts(int $companyId, array $groups): void
    {
        if (!isset($groups['wip'])) {
            $this->command->warn('WIP account group not found. Skipping WIP accounts.');
            return;
        }

        $wipAccounts = [
            [
                'code'  => 'WIP-MATERIAL',
                'name'  => 'WIP - Material',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'WIP-CONSUMABLES',
                'name'  => 'WIP - Consumables',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'WIP-SUBCON',
                'name'  => 'WIP - Subcontractor',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'WIP-LABOUR',
                'name'  => 'WIP - Labour (Phase 2)',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'WIP-MACHINE',
                'name'  => 'WIP - Machine (Phase 2)',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'WIP-OTHER',
                'name'  => 'WIP - Other Direct Costs',
                'type'  => 'ledger',
            ],
        ];

        foreach ($wipAccounts as $account) {
            Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $account['code']],
                [
                    'account_group_id' => $groups['wip']->id,
                    'name'             => $account['name'],
                    'type'             => $account['type'],
                    'is_active'        => true,
                    'is_system'        => true,
                    'opening_balance'  => 0,
                ]
            );
        }

        $this->command->info('Created WIP accounts.');
    }

    /**
     * Create Revenue accounts for sales posting
     */
    protected function createRevenueAccounts(int $companyId, array $groups): void
    {
        if (!isset($groups['revenue'])) {
            $this->command->warn('Revenue account group not found. Skipping revenue accounts.');
            return;
        }

        $revenueAccounts = [
            [
                'code'  => 'REV-FABRICATION',
                'name'  => 'Fabrication Revenue',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'REV-ERECTION',
                'name'  => 'Erection Revenue',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'REV-SUPPLY',
                'name'  => 'Supply Revenue',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'REV-SERVICE',
                'name'  => 'Service Revenue',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'REV-OTHER',
                'name'  => 'Other Revenue',
                'type'  => 'ledger',
            ],
        ];

        foreach ($revenueAccounts as $account) {
            Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $account['code']],
                [
                    'account_group_id' => $groups['revenue']->id,
                    'name'             => $account['name'],
                    'type'             => $account['type'],
                    'is_active'        => true,
                    'is_system'        => true,
                    'opening_balance'  => 0,
                ]
            );
        }

        $this->command->info('Created Revenue accounts.');
    }

    /**
     * Create Output GST accounts for sales posting
     */
    protected function createOutputGstAccounts(int $companyId, array $groups): void
    {
        if (!isset($groups['gst_output'])) {
            $this->command->warn('GST Output account group not found. Skipping GST output accounts.');
            return;
        }

        $gstOutputAccounts = [
            [
                'code'  => 'GST-CGST-OUTPUT',
                'name'  => 'Output CGST',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'GST-SGST-OUTPUT',
                'name'  => 'Output SGST',
                'type'  => 'ledger',
            ],
            [
                'code'  => 'GST-IGST-OUTPUT',
                'name'  => 'Output IGST',
                'type'  => 'ledger',
            ],
        ];

        foreach ($gstOutputAccounts as $account) {
            Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $account['code']],
                [
                    'account_group_id'  => $groups['gst_output']->id,
                    'name'              => $account['name'],
                    'type'              => $account['type'],
                    'is_active'         => true,
                    'is_system'         => true,
                    'is_gst_applicable' => false,
                    'opening_balance'   => 0,
                ]
            );
        }

        $this->command->info('Created Output GST accounts.');
    }

    /**
     * Create additional expense accounts
     */
    protected function createExpenseAccounts(int $companyId, array $groups): void
    {
        if (!isset($groups['expenses'])) {
            $this->command->warn('Direct Expenses group not found. Skipping expense accounts.');
            return;
        }

        $expenseAccounts = [
            [
                'code'  => 'FACTORY-CONSUMABLES',
                'name'  => 'Factory Consumables Expense',
                'type'  => 'ledger',
            ],
        ];

        foreach ($expenseAccounts as $account) {
            Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $account['code']],
                [
                    'account_group_id' => $groups['expenses']->id,
                    'name'             => $account['name'],
                    'type'             => $account['type'],
                    'is_active'        => true,
                    'is_system'        => false,
                    'opening_balance'  => 0,
                ]
            );
        }

        $this->command->info('Created additional expense accounts.');
    }
}
