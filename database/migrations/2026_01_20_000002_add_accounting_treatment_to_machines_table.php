<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        if (Schema::hasColumn('machines', 'accounting_treatment')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            // Nullable: when null, treat as material_type.accounting_usage (usually fixed_asset)
            $table->string('accounting_treatment', 30)
                ->nullable()
                ->after('purchase_price')
                ->comment('Optional override: fixed_asset|tool_stock');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        if (! Schema::hasColumn('machines', 'accounting_treatment')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            $table->dropColumn('accounting_treatment');
        });
    }
};
