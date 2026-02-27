<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AccountingMasterSeeder extends Seeder
{
    public function run(): void
    {
        // Use first company as default
        $companyId = Company::query()->value('id') ?? 1;

        $groupCache = [];

        $findGroupByCode = function (string $code) use (&$groupCache, $companyId) {
            if (isset($groupCache[$code])) {
                return $groupCache[$code];
            }

            $group = AccountGroup::where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($group) {
                $groupCache[$code] = $group;
            }

            return $group;
        };

        $upsertGroup = function (
            string $code,
            string $name,
            string $nature,
            ?string $parentCode = null,
            bool $isPrimary = false,
            int $sortOrder = 0
        ) use (&$groupCache, $findGroupByCode, $companyId) {
            $parentId = null;
            if ($parentCode) {
                $parent = $findGroupByCode($parentCode);
                $parentId = $parent?->id;
            }

            $group = AccountGroup::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code'       => $code,
                ],
                [
                    'name'       => $name,
                    'nature'     => $nature, // asset/liability/income/expense/equity
                    'parent_id'  => $parentId,
                    'is_primary' => $isPrimary,
                    'sort_order' => $sortOrder,
                ]
            );

            $groupCache[$code] = $group;

            return $group;
        };

        $upsertAccount = function (
            string $code,
            string $name,
            string $groupCode,
            string $type = 'ledger'
        ) use ($companyId, $findGroupByCode) {
            $group = $findGroupByCode($groupCode);

            if (! $group) {
                throw new \RuntimeException("Account group '{$groupCode}' not found when creating account '{$code}'.");
            }

            return Account::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code'       => $code,
                ],
                [
                    'account_group_id'     => $group->id,
                    'name'                 => $name,
                    'type'                 => $type, // ledger, bank, tax, inventory etc.
                    'is_active'            => true,
                    'opening_balance'      => 0,
                    'opening_balance_type' => 'dr',
                ]
            );
        };

        // Primary groups
        $upsertGroup('ASSETS',     'Assets',     'asset',    null, true, 10);
        $upsertGroup('LIABILITIES','Liabilities','liability',null, true, 20);
        $upsertGroup('EQUITY',     'Equity',     'equity',   null, true, 30);
        $upsertGroup('INCOME',     'Income',     'income',   null, true, 40);
        $upsertGroup('EXPENSES',   'Expenses',   'expense',  null, true, 50);

        // Asset sub-groups
        $upsertGroup('CURRENT_ASSETS',   'Current Assets', 'asset',   'ASSETS', false, 100);
        $upsertGroup('FIXED_ASSETS',      'Fixed Assets',       'asset',   'ASSETS', false, 120);
        $upsertGroup('INVESTMENTS',       'Investments',        'asset',   'ASSETS', false, 130);
        $upsertGroup('BANK_ACCOUNTS',    'Bank Accounts',  'asset',   'CURRENT_ASSETS', false, 110);
        $upsertGroup('CASH_IN_HAND',     'Cash-in-hand',   'asset',   'CURRENT_ASSETS', false, 120);
        $upsertGroup('SUNDRY_DEBTORS',   'Sundry Debtors', 'asset',   'CURRENT_ASSETS', false, 130);
        $upsertGroup('INVENTORY',        'Inventory',      'asset',   'CURRENT_ASSETS', false, 140);
        $upsertGroup('LOANS_ADVANCES',   'Loans & Advances','asset',  'CURRENT_ASSETS', false, 150);
        $upsertGroup('GST_INPUT_GROUP',  'GST Input',      'asset',   'CURRENT_ASSETS', false, 160);
        $upsertGroup('TCS_RECEIVABLE_G', 'TCS Receivable', 'asset',   'CURRENT_ASSETS', false, 170);

        // Liability sub-groups
        $upsertGroup('CAPITAL_ACCOUNT',   'Capital Account',    'equity',  'LIABILITIES', false, 180);
        $upsertGroup('LOANS_LIABILITY',   'Loans (Liability)',  'liability','LIABILITIES', false, 190);
        $upsertGroup('CURRENT_LIABILITIES','Current Liabilities', 'liability', 'LIABILITIES', false, 200);
        $upsertGroup('SUNDRY_CREDITORS',  'Sundry Creditors',     'liability', 'CURRENT_LIABILITIES', false, 210);
        $upsertGroup('DUTIES_TAXES',      'Duties & Taxes',       'liability', 'CURRENT_LIABILITIES', false, 220);
        $upsertGroup('TDS_PAYABLE_G',     'TDS Payable',          'liability', 'CURRENT_LIABILITIES', false, 230);

        // Income / Expense groups
        $upsertGroup('SALES',             'Sales Accounts',    'income',  'INCOME',   false, 300);
        $upsertGroup('OTHER_INCOME',      'Other Income',      'income',  'INCOME',   false, 310);

        $upsertGroup('DIRECT_EXPENSES',   'Direct Expenses',   'expense', 'EXPENSES', false, 400);
        $upsertGroup('INDIRECT_EXPENSES', 'Indirect Expenses', 'expense', 'EXPENSES', false, 410);
        $upsertGroup('CONSUMABLE_EXP',    'Consumables',       'expense', 'DIRECT_EXPENSES', false, 420);

        // Ledgers
        $upsertAccount('CASH',       'Cash in Hand',          'CASH_IN_HAND',  'cash');
        $upsertAccount('BANK-HDFC',  'HDFC Bank',             'BANK_ACCOUNTS', 'bank');

        // Equity / Capital
        $upsertAccount('CAPITAL',        'Current Capital',          'CAPITAL_ACCOUNT',  'ledger');
        $upsertAccount('SHARE-CAPITAL',  'Share Capital',            'CAPITAL_ACCOUNT',  'ledger');

        // Loans (Liability)
        $upsertAccount('BANK-OD',        'Bank OD A/C',              'LOANS_LIABILITY',  'bank');
        $upsertAccount('LOAN-SECURED',   'Secured Loans',            'LOANS_LIABILITY',  'ledger');
        $upsertAccount('LOAN-UNSECURED', 'Unsecured Loans',          'LOANS_LIABILITY',  'ledger');

        // Fixed Assets
        $upsertAccount('FA-BUILDING',    'Factory Building',         'FIXED_ASSETS',     'ledger');
        $upsertAccount('FA-GENERAL',     'Factory General Assets',   'FIXED_ASSETS',     'ledger');
        $upsertAccount('FA-MACHINERY',   'Machinery',                'FIXED_ASSETS',     'ledger');
        $upsertAccount('FA-VEHICLES',    'Vehicles',                 'FIXED_ASSETS',     'ledger');
        $upsertAccount('FA-OFFICE',      'Factory Office',           'FIXED_ASSETS',     'ledger');

        // Investments
        $upsertAccount('INV-HDFC-FDR',   'HDFC Bank Ltd FDR',         'INVESTMENTS',      'ledger');

        // Inventory & expense defaults
        $upsertAccount('INV-RM',     'Raw Material Inventory', 'INVENTORY',      'inventory');
        $upsertAccount('EXP-CONS',   'Consumables Expense',    'CONSUMABLE_EXP', 'ledger');

        // Tool Stock (Phase-C)
        $upsertAccount('INV-TOOLS',            'Tools Inventory',                      'INVENTORY',         'inventory');
        $upsertAccount('TOOLS-WITH-CONTRACTOR','Tools In Custody (Contractor/Worker)', 'INVENTORY',         'ledger');
        $upsertAccount('TOOLS-SCRAP-LOSS',     'Tools Scrap / Loss',                   'INDIRECT_EXPENSES', 'ledger');

        // Taxes
        $upsertAccount('GST-IN-CGST','Input CGST', 'GST_INPUT_GROUP', 'tax');
        $upsertAccount('GST-IN-SGST','Input SGST', 'GST_INPUT_GROUP', 'tax');
        $upsertAccount('GST-IN-IGST','Input IGST', 'GST_INPUT_GROUP', 'tax');

        $upsertAccount('TDS-PAYABLE',   'TDS Payable',   'TDS_PAYABLE_G',     'tax');
        $upsertAccount('TCS-RECEIVABLE','TCS Receivable','TCS_RECEIVABLE_G',  'tax');

        // Basic sales/purchase
        $upsertAccount('PURCHASE-RM', 'Raw Material Purchases', 'DIRECT_EXPENSES', 'ledger');
        $upsertAccount('FAB-REV',     'Fabrication Revenue',    'SALES',           'ledger');
    }
}
