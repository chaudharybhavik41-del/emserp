<?php

namespace Database\Seeders;

use App\Models\Accounting\AccountGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class AccountingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyId = (int) Config::get('accounting.default_company_id', 1);

        $groups = [
            [
                'code'   => 'CAPITAL',
                'name'   => 'Capital Account',
                'nature' => 'equity',
            ],
            [
                'code'   => 'SUNDRY_DEBTORS',
                'name'   => 'Sundry Debtors',
                'nature' => 'asset',
            ],
            [
                'code'   => 'SUNDRY_CREDITORS',
                'name'   => 'Sundry Creditors',
                'nature' => 'liability',
            ],
            [
                'code'   => 'BANK_ACCOUNTS',
                'name'   => 'Bank Accounts',
                'nature' => 'asset',
            ],
            [
                'code'   => 'DUTIES_TAXES',
                'name'   => 'Duties & Taxes',
                'nature' => 'liability',
            ],
            [
                'code'   => 'PROJECT_WIP',
                'name'   => 'Project WIP',
                'nature' => 'asset',
            ],
            [
                'code'   => 'SALES',
                'name'   => 'Sales / Revenue',
                'nature' => 'income',
            ],
            [
                'code'   => 'DIRECT_EXPENSES',
                'name'   => 'Direct Expenses',
                'nature' => 'expense',
            ],
        ];

        foreach ($groups as $index => $grp) {
            AccountGroup::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code'       => $grp['code'],
                ],
                [
                    'name'       => $grp['name'],
                    'nature'     => $grp['nature'],
                    'is_primary' => true,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
