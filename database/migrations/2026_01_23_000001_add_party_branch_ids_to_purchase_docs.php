<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase Orders: store which vendor GSTIN/branch was used (optional).
        if (Schema::hasTable('purchase_orders') && ! Schema::hasColumn('purchase_orders', 'vendor_branch_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('vendor_branch_id')
                    ->nullable()
                    ->after('vendor_party_id')
                    ->constrained('party_branches')
                    ->nullOnDelete();
            });
        }

        // Purchase Bills: store which supplier GSTIN/branch was used (optional).
        if (Schema::hasTable('purchase_bills') && ! Schema::hasColumn('purchase_bills', 'supplier_branch_id')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                $table->foreignId('supplier_branch_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('party_branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_bills') && Schema::hasColumn('purchase_bills', 'supplier_branch_id')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_branch_id');
            });
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'vendor_branch_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vendor_branch_id');
            });
        }
    }
};
