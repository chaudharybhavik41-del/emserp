<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot RCM flag at line-level so later GST config changes don't
        // alter historic bills.
        Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bill_expense_lines', 'is_reverse_charge')) {
                $table->boolean('is_reverse_charge')
                    ->default(false)
                    ->after('account_id');
            }
        });

        // Header-level RCM totals so posting + reporting can be done quickly.
        Schema::table('purchase_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bills', 'total_rcm_tax')) {
                $table->decimal('total_rcm_tax', 15, 2)->default(0)->after('total_igst');
            }
            if (! Schema::hasColumn('purchase_bills', 'total_rcm_cgst')) {
                $table->decimal('total_rcm_cgst', 15, 2)->default(0)->after('total_rcm_tax');
            }
            if (! Schema::hasColumn('purchase_bills', 'total_rcm_sgst')) {
                $table->decimal('total_rcm_sgst', 15, 2)->default(0)->after('total_rcm_cgst');
            }
            if (! Schema::hasColumn('purchase_bills', 'total_rcm_igst')) {
                $table->decimal('total_rcm_igst', 15, 2)->default(0)->after('total_rcm_sgst');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bill_expense_lines', 'is_reverse_charge')) {
                $table->dropColumn('is_reverse_charge');
            }
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $drops = [];
            foreach (['total_rcm_tax', 'total_rcm_cgst', 'total_rcm_sgst', 'total_rcm_igst'] as $col) {
                if (Schema::hasColumn('purchase_bills', $col)) {
                    $drops[] = $col;
                }
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
