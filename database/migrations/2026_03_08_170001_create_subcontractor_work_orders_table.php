<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractor_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('subcontractor_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('work_order_number', 100);
            $table->date('work_order_date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('security_deposit_percent', 5, 2)->default(0);
            $table->text('other_terms')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'work_order_number'], 'subcon_work_orders_company_number_unique');
            $table->index(['subcontractor_id', 'project_id'], 'subcon_work_orders_party_project_idx');
            $table->index('status', 'subcon_work_orders_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontractor_work_orders');
    }
};
