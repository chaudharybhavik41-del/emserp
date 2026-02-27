<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Material types: high-level accounting usage
        Schema::table('material_types', function (Blueprint $table) {
            $table->string('accounting_usage', 50)
                ->default('inventory') // inventory, expense, fixed_asset, mixed
                ->after('description');
        });

        // Material subcategories: default accounting accounts
        Schema::table('material_subcategories', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_account_id')->nullable()->after('description');
            $table->unsignedBigInteger('asset_account_id')->nullable()->after('expense_account_id');
            $table->unsignedBigInteger('inventory_account_id')->nullable()->after('asset_account_id');

            $table->foreign('expense_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->foreign('asset_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->foreign('inventory_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();
        });

        // Items: optional overrides per item
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_account_id')->nullable()->after('weight_per_meter');
            $table->unsignedBigInteger('asset_account_id')->nullable()->after('expense_account_id');
            $table->unsignedBigInteger('inventory_account_id')->nullable()->after('asset_account_id');

            $table->foreign('expense_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->foreign('asset_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();

            $table->foreign('inventory_account_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_account_id');
            $table->dropConstrainedForeignId('asset_account_id');
            $table->dropConstrainedForeignId('expense_account_id');
        });

        Schema::table('material_subcategories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_account_id');
            $table->dropConstrainedForeignId('asset_account_id');
            $table->dropConstrainedForeignId('expense_account_id');
        });

        Schema::table('material_types', function (Blueprint $table) {
            $table->dropColumn('accounting_usage');
        });
    }
};
