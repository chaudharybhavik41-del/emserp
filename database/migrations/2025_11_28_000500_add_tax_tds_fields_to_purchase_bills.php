<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bill_lines', function (Blueprint $table) {
            // GST split at line level
            $table->decimal('cgst_amount', 15, 2)->default(0)->after('tax_amount');
            $table->decimal('sgst_amount', 15, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 15, 2)->default(0)->after('sgst_amount');
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            // Header GST totals
            $table->decimal('total_cgst', 15, 2)->default(0)->after('total_tax');
            $table->decimal('total_sgst', 15, 2)->default(0)->after('total_cgst');
            $table->decimal('total_igst', 15, 2)->default(0)->after('total_sgst');

            // TDS fields (for supplier bills where TDS is deducted)
            $table->decimal('tds_rate', 8, 4)->default(0)->after('total_amount');
            $table->decimal('tds_amount', 15, 2)->default(0)->after('tds_rate');
            $table->string('tds_section', 20)->nullable()->after('tds_amount');

            // TCS fields (for suppliers charging TCS to us)
            $table->decimal('tcs_rate', 8, 4)->default(0)->after('tds_section');
            $table->decimal('tcs_amount', 15, 2)->default(0)->after('tcs_rate');
            $table->string('tcs_section', 20)->nullable()->after('tcs_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bill_lines', function (Blueprint $table) {
            $table->dropColumn(['cgst_amount', 'sgst_amount', 'igst_amount']);
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropColumn([
                'total_cgst',
                'total_sgst',
                'total_igst',
                'tds_rate',
                'tds_amount',
                'tds_section',
                'tcs_rate',
                'tcs_amount',
                'tcs_section',
            ]);
        });
    }
};
