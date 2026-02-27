<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_rfq_vendors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_rfq_id')
                ->constrained('purchase_rfqs')
                ->onDelete('cascade');

            $table->foreignId('vendor_party_id')
                ->constrained('parties')
                ->comment('Vendor (party) to whom RFQ was sent');

            $table->string('status', 20)->default('invited'); // invited/responded/rejected
            $table->string('email')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();

            $table->timestamps();

            $table->index(['purchase_rfq_id', 'vendor_party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfq_vendors');
    }
};
