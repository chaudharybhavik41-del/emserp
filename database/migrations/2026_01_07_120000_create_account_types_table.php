<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_types')) {
            return;
        }

        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'account_types_company_code_unique');
        });

        // Seed default types for all existing companies (safe insert).
        // This allows the Account "Type" dropdown to be data-driven.
        $defaultTypes = [
            ['code' => 'ledger',    'name' => 'Ledger (Generic)',                              'sort_order' => 10],
            ['code' => 'debtor',    'name' => 'Sundry Debtor (Customer)',                      'sort_order' => 20],
            ['code' => 'creditor',  'name' => 'Sundry Creditor (Supplier / Contractor)',       'sort_order' => 30],
            ['code' => 'party',     'name' => 'Party (Auto-managed from Party master)',        'sort_order' => 40],
            ['code' => 'bank',      'name' => 'Bank Account',                                  'sort_order' => 50],
            ['code' => 'cash',      'name' => 'Cash-in-hand',                                  'sort_order' => 60],
            ['code' => 'tax',       'name' => 'Tax / Duty',                                    'sort_order' => 70],
            ['code' => 'inventory', 'name' => 'Inventory',                                     'sort_order' => 80],
            ['code' => 'wip',       'name' => 'Work-in-progress',                              'sort_order' => 90],
            ['code' => 'income',    'name' => 'Income',                                        'sort_order' => 100],
            ['code' => 'expense',   'name' => 'Expense',                                       'sort_order' => 110],
        ];

        $companyIds = [1];

        if (Schema::hasTable('companies')) {
            $ids = DB::table('companies')->pluck('id')->all();
            if (! empty($ids)) {
                $companyIds = $ids;
            }
        }

        $now = now();

        foreach ($companyIds as $companyId) {
            foreach ($defaultTypes as $type) {
                DB::table('account_types')->updateOrInsert(
                    [
                        'company_id' => (int) $companyId,
                        'code'       => (string) $type['code'],
                    ],
                    [
                        'name'       => (string) $type['name'],
                        'is_system'  => true,
                        'is_active'  => true,
                        'sort_order' => (int) $type['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
