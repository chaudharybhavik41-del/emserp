<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('gst_type', 16)->nullable()->after('tax_percent');
            $table->decimal('cgst_percent', 5, 2)->nullable()->after('gst_type');
            $table->decimal('sgst_percent', 5, 2)->nullable()->after('cgst_percent');
            $table->decimal('igst_percent', 5, 2)->nullable()->after('sgst_percent');
            $table->decimal('cgst_amount', 15, 2)->nullable()->after('igst_percent');
            $table->decimal('sgst_amount', 15, 2)->nullable()->after('cgst_amount');
            $table->decimal('igst_amount', 15, 2)->nullable()->after('sgst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'gst_type',
                'cgst_percent',
                'sgst_percent',
                'igst_percent',
                'cgst_amount',
                'sgst_amount',
                'igst_amount',
            ]);
        });
    }
};
