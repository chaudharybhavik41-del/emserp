<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartyBanksTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('party_banks')) {
            return;
        }

        Schema::create('party_banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('party_id');

            $table->string('bank_name', 150);
            $table->string('branch', 150)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->string('upi_id', 100)->nullable();

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->foreign('party_id')
                ->references('id')->on('parties')
                ->cascadeOnDelete();

            $table->index(['party_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_banks');
    }
}
