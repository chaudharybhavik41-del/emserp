<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bill_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bill_lines', 'serial_no')) {
                $table->string('serial_no')->nullable()->after('account_id');
            }

            if (! Schema::hasColumn('purchase_bill_lines', 'machine_make')) {
                $table->string('machine_make')->nullable()->after('serial_no');
            }

            if (! Schema::hasColumn('purchase_bill_lines', 'machine_model')) {
                $table->string('machine_model')->nullable()->after('machine_make');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bill_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bill_lines', 'machine_model')) {
                $table->dropColumn('machine_model');
            }
            if (Schema::hasColumn('purchase_bill_lines', 'machine_make')) {
                $table->dropColumn('machine_make');
            }
            if (Schema::hasColumn('purchase_bill_lines', 'serial_no')) {
                $table->dropColumn('serial_no');
            }
        });
    }
};
