<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        if (Schema::hasColumn('items', 'accounting_usage_override')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->string('accounting_usage_override', 50)
                ->nullable()
                ->after('inventory_account_id')
                ->comment('Optional override: inventory|expense|fixed_asset|mixed|tool_stock');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        if (! Schema::hasColumn('items', 'accounting_usage_override')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('accounting_usage_override');
        });
    }
};
