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
            if (! Schema::hasColumn('vouchers', 'payment_type')) {
                $table->string('payment_type', 30)->nullable()->after('voucher_type');
                $table->index('payment_type', 'idx_vouchers_payment_type');
            }

            if (! Schema::hasColumn('vouchers', 'purchase_order_id')) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->after('payment_type');
                $table->index('purchase_order_id', 'idx_vouchers_purchase_order_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vouchers')) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'purchase_order_id')) {
                $table->dropIndex('idx_vouchers_purchase_order_id');
                $table->dropColumn('purchase_order_id');
            }

            if (Schema::hasColumn('vouchers', 'payment_type')) {
                $table->dropIndex('idx_vouchers_payment_type');
                $table->dropColumn('payment_type');
            }
        });
    }
};
