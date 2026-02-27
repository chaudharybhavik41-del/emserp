<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            $table->unsignedInteger('revision_no')->default(1)->after('purchase_rfq_item_id');
            $table->boolean('is_active')->default(true)->after('revision_no');
            $table->string('vendor_quote_no')->nullable()->after('is_active');
            $table->date('vendor_quote_date')->nullable()->after('vendor_quote_no');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            $table->dropColumn(['revision_no', 'is_active', 'vendor_quote_no', 'vendor_quote_date']);
        });
    }
};
