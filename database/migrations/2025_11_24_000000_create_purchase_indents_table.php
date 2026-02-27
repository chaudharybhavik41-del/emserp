<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_indents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Indent number, e.g. IND-25-0001');

            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->foreignId('department_id')->nullable()->constrained('departments');

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->date('required_by_date')->nullable();
            $table->string('status', 20)->default('draft'); // draft/submitted/approved/rejected/closed

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['status', 'required_by_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_indents');
    }
};
