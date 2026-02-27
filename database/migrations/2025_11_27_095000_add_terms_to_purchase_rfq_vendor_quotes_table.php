<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            // Payment terms in days (e.g. 30, 45, 60)
            $table->unsignedInteger('payment_terms_days')
                ->nullable()
                ->after('tax_percent');

            // You already have delivery_days column from earlier migrations,
            // so we don't re-add it here.

            // Freight terms text, e.g. "FOR Site", "Ex-Works", etc.
            $table->string('freight_terms', 255)
                ->nullable()
                ->after('delivery_days');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            $table->dropColumn(['payment_terms_days', 'freight_terms']);
        });
    }
};
