<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bill_expense_lines', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('account_id');
                $table->index('project_id', 'pb_exp_lines_project_idx');
                $table->foreign('project_id', 'pb_exp_lines_project_fk')
                    ->references('id')->on('projects')
                    ->nullOnDelete();
            }
        });

        // Backfill: if bill header has a project_id, copy to expense lines.
        // This preserves Phase-A behaviour for existing data.
        try {
            DB::statement(
                "UPDATE purchase_bill_expense_lines el\n" .
                "JOIN purchase_bills b ON b.id = el.purchase_bill_id\n" .
                "SET el.project_id = b.project_id\n" .
                "WHERE el.project_id IS NULL AND b.project_id IS NOT NULL"
            );
        } catch (\Throwable $e) {
            // Safe no-op (some environments may block statements during migration)
        }
    }

    public function down(): void
    {
        Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bill_expense_lines', 'project_id')) {
                try {
                    $table->dropForeign('pb_exp_lines_project_fk');
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    $table->dropIndex('pb_exp_lines_project_idx');
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('project_id');
            }
        });
    }
};
