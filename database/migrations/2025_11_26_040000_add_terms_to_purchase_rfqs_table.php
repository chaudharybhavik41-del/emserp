<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_rfqs', function (Blueprint $table) {
            $table->unsignedInteger('payment_terms_days')->nullable()->after('due_date');
            $table->unsignedInteger('delivery_terms_days')->nullable()->after('payment_terms_days');
            $table->string('freight_terms', 255)->nullable()->after('delivery_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_rfqs', function (Blueprint $table) {
            $table->dropColumn(['payment_terms_days', 'delivery_terms_days', 'freight_terms']);
        });
    }
};
