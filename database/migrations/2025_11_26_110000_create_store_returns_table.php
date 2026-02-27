<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_returns')) {
            return;
        }

        Schema::create('store_returns', function (Blueprint $table) {
            $table->id();

            $table->string('return_number', 50)->nullable()->unique();
            $table->date('return_date');

            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_issue_id')->nullable();

            $table->unsignedBigInteger('contractor_party_id')->nullable();
            $table->string('contractor_person_name', 100)->nullable();

            $table->string('status', 30)->default('posted');
            $table->string('reason', 255)->nullable();
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_returns');
    }
};
