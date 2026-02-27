<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add allocation_date column (Doctrine/DBAL not required)
        if (! Schema::hasColumn('account_bill_allocations', 'allocation_date')) {
            Schema::table('account_bill_allocations', function (Blueprint $table) {
                $table->date('allocation_date')->nullable()->after('amount');
            });
        }

        // 2) Backfill allocation_date from voucher.voucher_date (best-effort)
        //    NOTE: Works even if some rows already have allocation_date populated.
        try {
            DB::statement(
                "UPDATE account_bill_allocations aba
                 JOIN vouchers v ON v.id = aba.voucher_id
                 SET aba.allocation_date = DATE(v.voucher_date)
                 WHERE aba.allocation_date IS NULL"
            );
        } catch (Throwable $e) {
            // ignore - table/columns may differ in some environments
        }

        // If any rows still NULL, fall back to created_at date (avoid NULL in day-to-day reporting)
        try {
            DB::statement(
                "UPDATE account_bill_allocations
                 SET allocation_date = DATE(created_at)
                 WHERE allocation_date IS NULL"
            );
        } catch (Throwable $e) {
            // ignore
        }

        // 3) IMPORTANT MySQL rule:
        //    You cannot drop an index that is being used to satisfy a foreign key constraint.
        //    Your FK is on voucher_line_id, and uq_bill_alloc_vline_bill was the ONLY index starting with voucher_line_id.
        //    So we create an index starting with voucher_line_id first, then safely drop the old unique, then add the new one.

        // Ensure a supporting index exists for FK (voucher_line_id,...)
        // (We will use the same index name Phase 8 wanted, but create it BEFORE dropping the old unique.)
        try {
            DB::statement(
                "CREATE INDEX idx_bill_alloc_vline_date
                 ON account_bill_allocations (voucher_line_id, allocation_date)"
            );
        } catch (Throwable $e) {
            // ignore if already exists
        }

        // Drop the old unique (now safe because FK is satisfied by idx_bill_alloc_vline_date)
        try {
            DB::statement("ALTER TABLE account_bill_allocations DROP INDEX uq_bill_alloc_vline_bill");
        } catch (Throwable $e) {
            // ignore if already dropped / doesn't exist
        }

        // Create the new unique (allows multiple allocations per bill across different allocation_date and/or mode)
        try {
            DB::statement(
                "ALTER TABLE account_bill_allocations
                 ADD UNIQUE INDEX uq_bill_alloc_vline_bill_mode_date (voucher_line_id, bill_type, bill_id, mode, allocation_date)"
            );
        } catch (Throwable $e) {
            // ignore if already exists
        }

        // Other helpful indexes for report performance
        try {
            DB::statement(
                "CREATE INDEX idx_bill_alloc_company_account_date
                 ON account_bill_allocations (company_id, account_id, allocation_date)"
            );
        } catch (Throwable $e) {
            // ignore if already exists
        }
    }

    public function down(): void
    {
        // Best-effort rollback (safe in case objects are already missing)
        try {
            DB::statement("ALTER TABLE account_bill_allocations DROP INDEX idx_bill_alloc_company_account_date");
        } catch (Throwable $e) {}

        try {
            DB::statement("ALTER TABLE account_bill_allocations DROP INDEX uq_bill_alloc_vline_bill_mode_date");
        } catch (Throwable $e) {}

        // Restore old unique (optional)
        try {
            DB::statement(
                "ALTER TABLE account_bill_allocations
                 ADD UNIQUE INDEX uq_bill_alloc_vline_bill (voucher_line_id, bill_type, bill_id)"
            );
        } catch (Throwable $e) {}

        // Drop the vline index created in this migration (optional)
        try {
            DB::statement("ALTER TABLE account_bill_allocations DROP INDEX idx_bill_alloc_vline_date");
        } catch (Throwable $e) {}

        if (Schema::hasColumn('account_bill_allocations', 'allocation_date')) {
            Schema::table('account_bill_allocations', function (Blueprint $table) {
                $table->dropColumn('allocation_date');
            });
        }
    }
};
