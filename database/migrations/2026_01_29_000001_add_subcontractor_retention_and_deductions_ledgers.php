<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;

return new class extends Migration
{
    public function up(): void
    {
        // Single-company install (as per current project stage)
        $companyId = (int) Config::get('accounting.default_company_id', 1);

        if (! class_exists(\App\Models\Accounting\Account::class) || ! class_exists(\App\Models\Accounting\AccountGroup::class)) {
            return;
        }

        $creditorsGroup = \App\Models\Accounting\AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', 'SUNDRY_CREDITORS')
            ->first();

        $otherIncomeGroup = \App\Models\Accounting\AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', 'OTHER_INCOME')
            ->first();

        // 1) Retention Payable (liability)
        if ($creditorsGroup) {
            \App\Models\Accounting\Account::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code'       => 'RETENTION-PAYABLE',
                ],
                [
                    'account_group_id'      => $creditorsGroup->id,
                    'name'                  => 'Retention Payable',
                    'type'                  => 'ledger',
                    'is_active'             => true,
                    'is_system'             => true,
                    'system_key'            => 'retention_payable',
                    'opening_balance'       => 0,
                    'opening_balance_type'  => 'cr',
                ]
            );
        }

        // 2) Other Deductions / Recoveries from Subcontractors
        // (defaulted under Other Income; you can re-group it later if your accounting prefers a different classification)
        if ($otherIncomeGroup) {
            \App\Models\Accounting\Account::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'code'       => 'SUBCON-DEDUCTIONS',
                ],
                [
                    'account_group_id'      => $otherIncomeGroup->id,
                    'name'                  => 'Subcontractor Deductions / Recoveries',
                    'type'                  => 'ledger',
                    'is_active'             => true,
                    'is_system'             => true,
                    'system_key'            => 'subcontractor_deductions',
                    'opening_balance'       => 0,
                    'opening_balance_type'  => 'cr',
                ]
            );
        }
    }

    public function down(): void
    {
        // Intentionally NO deletes. Ledgers may have transactions.
    }
};
