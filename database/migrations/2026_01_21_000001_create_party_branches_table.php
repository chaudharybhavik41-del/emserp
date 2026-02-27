<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('party_branches')) {
            return;
        }

        Schema::create('party_branches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('party_id')
                ->constrained('parties')
                ->cascadeOnDelete();

            $table->string('branch_name', 150)->nullable();
            $table->string('gstin', 20)->nullable();

            // Optional branch address (often required when GSTIN differs by state)
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('country', 100)->nullable()->default('India');

            // GST snapshot fields (future: fetch GSTIN details per branch)
            $table->string('gst_legal_name', 250)->nullable();
            $table->string('gst_trade_name', 250)->nullable();
            $table->string('gst_status', 50)->nullable();
            $table->string('gst_state_code', 10)->nullable();
            $table->text('gst_raw_json')->nullable();

            $table->timestamps();

            // A GSTIN must never repeat across branches; NULL allowed
            $table->unique(['gstin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_branches');
    }
};
