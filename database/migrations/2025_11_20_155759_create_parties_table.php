<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartiesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parties')) {
            return;
        }

        Schema::create('parties', function (Blueprint $table) {
            $table->id();

            // Classification flags
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_contractor')->default(false);
            $table->boolean('is_client')->default(false);

            // Basic identity
            $table->string('code', 50)->unique();     // we can add auto-code generator later
            $table->string('name', 200);              // display name
            $table->string('legal_name', 250)->nullable(); // legal entity name

            // Govt identifiers
            $table->string('gstin', 20)->nullable()->index();
            $table->string('pan', 20)->nullable();
            $table->string('msme_no', 50)->nullable();

            // Primary contact
            $table->string('primary_phone', 50)->nullable();
            $table->string('primary_email', 150)->nullable();

            // Address (billing / registered)
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('country', 100)->default('India');

            // GST snapshot fields (for when we integrate API)
            $table->string('gst_legal_name', 250)->nullable();
            $table->string('gst_trade_name', 250)->nullable();
            $table->string('gst_status', 50)->nullable();
            $table->string('gst_state_code', 10)->nullable();
            $table->text('gst_raw_json')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
}
