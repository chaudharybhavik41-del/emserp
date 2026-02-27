<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Store round off on purchase bills
        if (Schema::hasTable('purchase_bills') && ! Schema::hasColumn('purchase_bills', 'round_off')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                // NOTE: round_off can be +ve or -ve.
                // If invoice total is rounded UP, round_off is positive (extra Dr to Round Off).
                // If invoice total is rounded DOWN, round_off is negative (extra Cr to Round Off).
                $table->decimal('round_off', 15, 2)->default(0)->after('total_amount');
            });
        }

        // 2) Ensure a default "Round Off" ledger exists (Tally-style)
        //    Used by PurchaseBillPostingService when round_off != 0.
        if (
            Schema::hasTable('accounts') &&
            Schema::hasTable('account_groups') &&
            Schema::hasTable('companies')
        ) {
            $groupId = DB::table('account_groups')->where('code', 'INDIRECT_EXPENSES')->value('id');

            if ($groupId) {
                $companyIds = DB::table('companies')->pluck('id');

                foreach ($companyIds as $companyId) {
                    $exists = DB::table('accounts')
                        ->where('company_id', $companyId)
                        ->where('code', 'ROUND-OFF')
                        ->exists();

                    if (! $exists) {
                        DB::table('accounts')->insert([
                            'company_id'           => $companyId,
                            'account_group_id'     => $groupId,
                            'name'                 => 'Round Off',
                            'code'                 => 'ROUND-OFF',
                            'type'                 => 'ledger',
                            'is_active'            => 1,
                            'is_system'            => 1,
                            'system_key'           => 'ROUND_OFF',
                            'opening_balance'      => 0,
                            'opening_balance_type' => 'dr',
                            'opening_balance_date' => null,
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_bills') && Schema::hasColumn('purchase_bills', 'round_off')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                $table->dropColumn('round_off');
            });
        }

        // We intentionally do NOT delete the ROUND-OFF ledger in down(),
        // because it may already be used in vouchers and reports.
    }
};
