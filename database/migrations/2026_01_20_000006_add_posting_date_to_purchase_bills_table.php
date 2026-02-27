<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_bills')) {
            return;
        }

        if (! Schema::hasColumn('purchase_bills', 'posting_date')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                $table->date('posting_date')->nullable()->after('bill_date');
                $table->index('posting_date');
            });
        }

        // Backfill: for existing bills, posting_date should default to bill_date
        DB::statement("UPDATE purchase_bills SET posting_date = bill_date WHERE posting_date IS NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_bills')) {
            return;
        }

        if (Schema::hasColumn('purchase_bills', 'posting_date')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                $table->dropIndex(['posting_date']);
                $table->dropColumn('posting_date');
            });
        }
    }
};
