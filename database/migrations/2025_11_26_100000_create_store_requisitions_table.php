<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_requisitions')) {
            return;
        }

        Schema::create('store_requisitions', function (Blueprint $table) {
            $table->id();

            $table->string('requisition_number', 50)->nullable()->unique();
            $table->date('requisition_date');

            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contractor_party_id')->nullable();
            $table->string('contractor_person_name', 100)->nullable();

            $table->unsignedBigInteger('requested_by_user_id')->nullable();

            $table->string('status', 30)->default('requested'); // requested, approved, closed, cancelled

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisitions');
    }
};
