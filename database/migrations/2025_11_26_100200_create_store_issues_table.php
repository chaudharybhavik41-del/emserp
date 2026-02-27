<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_issues')) {
            return;
        }

        Schema::create('store_issues', function (Blueprint $table) {
            $table->id();

            $table->string('issue_number', 50)->nullable()->unique();
            $table->date('issue_date');

            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_requisition_id')->nullable();

            $table->unsignedBigInteger('contractor_party_id')->nullable();
            $table->string('contractor_person_name', 100)->nullable();

            $table->unsignedBigInteger('issued_to_user_id')->nullable(); // company employee, optional

            $table->string('status', 30)->default('posted'); // posted, cancelled

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_issues');
    }
};
