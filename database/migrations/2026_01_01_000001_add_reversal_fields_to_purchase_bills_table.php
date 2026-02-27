<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_bills', 'reversal_voucher_id')) {
                $table->unsignedBigInteger('reversal_voucher_id')->nullable()->after('voucher_id');
            }
            if (!Schema::hasColumn('purchase_bills', 'reversed_at')) {
                $table->dateTime('reversed_at')->nullable()->after('reversal_voucher_id');
            }
            if (!Schema::hasColumn('purchase_bills', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
            }
            if (!Schema::hasColumn('purchase_bills', 'reversal_reason')) {
                $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bills', 'reversal_reason')) {
                $table->dropColumn('reversal_reason');
            }
            if (Schema::hasColumn('purchase_bills', 'reversed_by')) {
                $table->dropColumn('reversed_by');
            }
            if (Schema::hasColumn('purchase_bills', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
            if (Schema::hasColumn('purchase_bills', 'reversal_voucher_id')) {
                $table->dropColumn('reversal_voucher_id');
            }
        });
    }
};
