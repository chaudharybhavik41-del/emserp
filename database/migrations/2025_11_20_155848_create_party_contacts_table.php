<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartyContactsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('party_contacts')) {
            return;
        }

        Schema::create('party_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('party_id');

            $table->string('name', 150);
            $table->string('designation', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();

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
        Schema::dropIfExists('party_contacts');
    }
}
