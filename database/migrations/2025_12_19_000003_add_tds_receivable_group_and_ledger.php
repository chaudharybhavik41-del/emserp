<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('account_groups') || ! Schema::hasTable('accounts')) {
            return;
        }

        $now = now();

        $companies = DB::table('companies')->select('id')->get();

        foreach ($companies as $company) {
            $companyId = (int) $company->id;

            // Parent group: CURRENT_ASSETS (fallback to LOANS_ADVANCES)
            $parentId = DB::table('account_groups')
                ->where('company_id', $companyId)
                ->where('code', 'CURRENT_ASSETS')
                ->value('id');

            if (! $parentId) {
                $parentId = DB::table('account_groups')
                    ->where('company_id', $companyId)
                    ->where('code', 'LOANS_ADVANCES')
                    ->value('id');
            }

            if (! $parentId) {
                // If chart isn't seeded yet, skip.
                continue;
            }

            $groupId = DB::table('account_groups')
                ->where('company_id', $companyId)
                ->where('code', 'TDS_RECEIVABLE_G')
                ->value('id');

            if (! $groupId) {
                $groupId = DB::table('account_groups')->insertGetId([
                    'company_id' => $companyId,
                    'name'       => 'TDS Receivable',
                    'code'       => 'TDS_RECEIVABLE_G',
                    'parent_id'  => $parentId,
                    'nature'     => 'asset',
                    'is_primary' => false,
                    'sort_order' => 165,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ledgerCode = 'TDS-RECEIVABLE';

            $accountExists = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('code', $ledgerCode)
                ->exists();

            if (! $accountExists) {
                DB::table('accounts')->insert([
                    'company_id'           => $companyId,
                    'account_group_id'     => $groupId,
                    'name'                 => 'TDS Receivable',
                    'code'                 => $ledgerCode,
                    'type'                 => 'tax',
                    'is_active'            => true,
                    'opening_balance'      => 0,
                    'opening_balance_type' => 'dr',
                    'gstin'                => null,
                    'pan'                  => null,
                    'credit_limit'         => null,
                    'credit_days'          => null,
                    'related_model_type'   => null,
                    'related_model_id'     => null,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('account_groups') || ! Schema::hasTable('accounts')) {
            return;
        }

        $companies = DB::table('companies')->select('id')->get();

        foreach ($companies as $company) {
            $companyId = (int) $company->id;

            DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('code', 'TDS-RECEIVABLE')
                ->delete();

            DB::table('account_groups')
                ->where('company_id', $companyId)
                ->where('code', 'TDS_RECEIVABLE_G')
                ->delete();
        }
    }
};
