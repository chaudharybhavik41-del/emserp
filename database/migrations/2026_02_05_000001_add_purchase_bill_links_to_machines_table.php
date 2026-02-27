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

        Schema::table('machines', function (Blueprint $table) {
            if (! Schema::hasColumn('machines', 'purchase_bill_id')) {
                $table->foreignId('purchase_bill_id')
                    ->nullable()
                    ->after('purchase_invoice_no')
                    ->constrained('purchase_bills')
                    ->nullOnDelete();

                $table->index('purchase_bill_id', 'idx_machines_purchase_bill');
            }

            if (! Schema::hasColumn('machines', 'purchase_bill_line_id')) {
                $table->foreignId('purchase_bill_line_id')
                    ->nullable()
                    ->after('purchase_bill_id')
                    ->constrained('purchase_bill_lines')
                    ->nullOnDelete();

                $table->index('purchase_bill_line_id', 'idx_machines_purchase_bill_line');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('machines')) {
            return;
        }

        Schema::table('machines', function (Blueprint $table) {
            if (Schema::hasColumn('machines', 'purchase_bill_line_id')) {
                try { $table->dropForeign(['purchase_bill_line_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex('idx_machines_purchase_bill_line'); } catch (\Throwable $e) {}
                $table->dropColumn('purchase_bill_line_id');
            }

            if (Schema::hasColumn('machines', 'purchase_bill_id')) {
                try { $table->dropForeign(['purchase_bill_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex('idx_machines_purchase_bill'); } catch (\Throwable $e) {}
                $table->dropColumn('purchase_bill_id');
            }
        });
    }
};
