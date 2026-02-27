<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) New GST history table (per item)
        Schema::create('gst_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->decimal('cgst_rate', 5, 2)->default(0); // e.g. 9.00
            $table->decimal('sgst_rate', 5, 2)->default(0); // e.g. 9.00
            $table->decimal('igst_rate', 5, 2)->default(0); // e.g. 18.00

            $table->timestamps();
        });

        // 2) Add HSN + GST % to items (for user convenience)
        Schema::table('items', function (Blueprint $table) {
            // adjust `after()` positions if needed
            $table->string('hsn_code', 50)
                ->nullable()
                ->after('spec');

            $table->decimal('gst_rate_percent', 5, 2)
                ->nullable()
                ->after('hsn_code');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'gst_rate_percent']);
        });

        Schema::dropIfExists('gst_tax_rates');
    }
};
