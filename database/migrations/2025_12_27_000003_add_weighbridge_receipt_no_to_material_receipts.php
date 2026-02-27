<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('material_receipts', 'weighbridge_receipt_no')) {
                $table->string('weighbridge_receipt_no', 100)->nullable()->after('vehicle_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('material_receipts', 'weighbridge_receipt_no')) {
                $table->dropColumn('weighbridge_receipt_no');
            }
        });
    }
};
