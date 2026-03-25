<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_client_billing_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('line_type', 40);
            $table->string('source_key', 120)->nullable();
            $table->string('description', 255)->nullable();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->decimal('rate', 15, 2)->default(0);
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('sac_hsn_code', 20)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'line_type', 'source_key'], 'idx_project_client_billing_rate_lookup');
            $table->index(['project_id', 'is_active'], 'idx_project_client_billing_rate_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_client_billing_rates');
    }
};
