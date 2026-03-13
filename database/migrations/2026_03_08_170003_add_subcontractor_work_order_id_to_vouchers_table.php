<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'subcontractor_work_order_id')) {
                $table->unsignedBigInteger('subcontractor_work_order_id')->nullable()->after('purchase_order_id');
                $table->index('subcontractor_work_order_id', 'idx_vouchers_subcontractor_work_order_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vouchers')) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'subcontractor_work_order_id')) {
                $table->dropIndex('idx_vouchers_subcontractor_work_order_id');
                $table->dropColumn('subcontractor_work_order_id');
            }
        });
    }
};
