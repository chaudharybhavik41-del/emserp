<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_indents', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_indents', 'procurement_status')) {
                // Keep approval workflow in `status` (draft/approved/rejected/closed/cancelled etc.)
                // Track procurement progress separately here (open/rfq_created/partially_ordered/ordered/closed).
                $table->string('procurement_status', 30)->default('open');
                $table->index(['procurement_status']);
            }
        });

        // Backfill for existing rows
        if (Schema::hasColumn('purchase_indents', 'procurement_status')) {
            DB::table('purchase_indents')
                ->whereNull('procurement_status')
                ->update(['procurement_status' => 'open']);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_indents', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_indents', 'procurement_status')) {
                $table->dropIndex(['procurement_status']);
                $table->dropColumn('procurement_status');
            }
        });
    }
};
