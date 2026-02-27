<?php

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class CheckAccountingConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan accounting:check-config
     */
    protected $signature = 'accounting:check-config';

    /**
     * The console command description.
     */
    protected $description = 'Validate accounting config (config/accounting.php) and referenced ledgers/groups/models';

    public function handle(): int
    {
        $this->info('Checking accounting config (config/accounting.php)...');
        $this->line('');

        $errors   = 0;
        $warnings = 0;

        /*
         |--------------------------------------------------------------------------
         | 1. Default company
         |--------------------------------------------------------------------------
         */
        $companyId = (int) Config::get('accounting.default_company_id');

        if (! $companyId) {
            $this->error('config("accounting.default_company_id") is not set or is zero.');
            $errors++;
        } else {
            $company = Company::find($companyId);

            if (! $company) {
                $this->error('Default company not found: id ' . $companyId . ' (check accounting.default_company_id).');
                $errors++;
            } else {
                $this->info('✔ Default company: [' . $company->id . '] ' . ($company->name ?? '(no name)'));
            }
        }

        $this->line('');

        /*
         |--------------------------------------------------------------------------
         | 2. Ledger codes that MUST exist in accounts table
         |--------------------------------------------------------------------------
         */
        $accountCodeChecks = [
            'default_accounts.inventory_raw_material_code'      => 'Default raw material inventory account',
            'default_accounts.consumables_expense_code'        => 'Default consumables expense account',

            'gst.input_cgst_account_code'                      => 'GST Input CGST account',
            'gst.input_sgst_account_code'                      => 'GST Input SGST account',
            'gst.input_igst_account_code'                      => 'GST Input IGST account',

            'tds.tds_payable_account_code'                     => 'TDS payable account',

            'tds.tds_receivable_account_code'                 => 'TDS receivable account',
            'tcs.tcs_receivable_account_code'                  => 'TCS receivable account',

            'store.project_wip_material_account_code'          => 'Project WIP - material account',
            'store.factory_consumable_expense_account_code'    => 'Factory consumable expense account',
            'store.inventory_consumables_account_code'         => 'Inventory consumables account (fallback for store issues)',
        ];

        foreach ($accountCodeChecks as $configPath => $label) {
            $fullKey = 'accounting.' . $configPath;
            $code    = Config::get($fullKey);

            if (! $code) {
                $this->error('[' . $label . '] Missing config: ' . $fullKey);
                $errors++;
                continue;
            }

            /** @var \App\Models\Accounting\Account|null $account */
            $account = Account::where('code', $code)->first();

            if (! $account) {
                $this->error('[' . $label . '] Account with code "' . $code . '" not found in accounts table.');
                $errors++;
            } else {
                $this->info('✔ ' . $label . ': code "' . $code . '" -> [' . $account->id . '] ' . $account->name);
            }
        }

        $this->line('');

        /*
         |--------------------------------------------------------------------------
         | 3. Cashflow-related groups (optional but recommended)
         |--------------------------------------------------------------------------
         */
        $cashGroupCodes = (array) Config::get('accounting.cashflow_cash_group_codes', []);

        if (empty($cashGroupCodes)) {
            $this->warn('No accounting.cashflow_cash_group_codes configured; cashflow reports will rely on account types only.');
            $warnings++;
        } else {
            $this->info('Checking cash/bank group codes for Cash Flow / Fund Flow:');
            foreach ($cashGroupCodes as $groupCode) {
                /** @var \App\Models\Accounting\AccountGroup|null $group */
                $group = AccountGroup::where('code', $groupCode)->first();

                if (! $group) {
                    $this->warn('• Group code "' . $groupCode . '" not found in account_groups table.');
                    $warnings++;
                } else {
                    $this->info('✔ Group code "' . $groupCode . '" -> [' . $group->id . '] ' . $group->name);
                }
            }
        }

        $this->line('');

        /*
         |--------------------------------------------------------------------------
         | 4. AR bill model (for Client Outstanding / Ageing)
         |--------------------------------------------------------------------------
         */
        $arBillModel = Config::get('accounting.ar_bill_model');

        if (! $arBillModel) {
            $this->line('AR bill model (accounting.ar_bill_model) is not configured yet. This is OK until client RA/invoices are wired.');
        } else {
            $this->info('Checking AR bill model: ' . $arBillModel);

            if (! class_exists($arBillModel)) {
                $this->warn('AR bill model class not found: ' . $arBillModel);
                $warnings++;
            } elseif (! is_subclass_of($arBillModel, Model::class)) {
                $this->warn('AR bill model is not an Eloquent model: ' . $arBillModel);
                $warnings++;
            } else {
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $arBillModel();
                $table = $model->getTable();

                if (! Schema::hasTable($table)) {
                    $this->warn('AR bill model table "' . $table . '" does not exist in the database.');
                    $warnings++;
                } else {
                    $this->info('✔ AR bill model OK, table "' . $table . '" exists.');
                }
            }
        }

        $this->line('');

        /*
         |--------------------------------------------------------------------------
         | 5. Store Issue posting flag
         |--------------------------------------------------------------------------
         */
        $storeIssuePostingEnabled = Config::get('accounting.enable_store_issue_posting', false);
        $this->line('Store issue posting: ' . ($storeIssuePostingEnabled ? 'ENABLED' : 'disabled') . ' (accounting.enable_store_issue_posting)');

        $this->line('');
        $this->info('Summary:');

        if ($errors === 0) {
            $this->info('✔ No blocking errors in accounting config.');
        } else {
            $this->error('✖ Found ' . $errors . ' error(s) in accounting config.');
        }

        if ($warnings > 0) {
            $this->warn('⚠ ' . $warnings . ' warning(s). These will not stop posting but should be reviewed.');
        }

        $this->line('');

        return $errors === 0 ? 0 : 1;
    }
}
