<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Approval timestamp (this is the column missing in your DB right now)
            if (!Schema::hasColumn('purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            // Optional cancel audit columns (controller already writes these when present)
            if (!Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('purchase_orders', 'cancelled_by')) {
                // Do NOT enforce FK to users table (keeps it safe across existing DBs)
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('purchase_orders', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Down migrations are optional, but keeping them safe
            if (Schema::hasColumn('purchase_orders', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
            if (Schema::hasColumn('purchase_orders', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('purchase_orders', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
