<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_rfqs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('RFQ number, e.g. RFQ-25-0001');

            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->foreignId('department_id')->nullable()->constrained('departments');

            $table->foreignId('created_by')->nullable()->constrained('users');

            $table->date('rfq_date')->nullable();
            $table->date('due_date')->nullable();

            $table->string('status', 20)->default('draft'); // draft/sent/closed/cancelled
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['status', 'rfq_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfqs');
    }
};
