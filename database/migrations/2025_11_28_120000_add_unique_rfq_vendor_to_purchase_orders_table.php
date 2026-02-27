<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Prevent duplicate PO for the same RFQ + vendor
            $table->unique(
                ['purchase_rfq_id', 'vendor_party_id'],
                'po_rfq_vendor_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('po_rfq_vendor_unique');
        });
    }
};
