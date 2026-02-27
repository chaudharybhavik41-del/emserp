<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_bills')) {
            Schema::create('production_bills', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('contractor_party_id')->constrained('parties')->restrictOnDelete();

                $table->string('bill_number', 60)->unique();
                $table->date('bill_date')->nullable();

                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();

                $table->enum('status', ['draft', 'finalized', 'cancelled'])->default('draft');

                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('grand_total', 14, 2)->default(0);

                $table->text('remarks')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'contractor_party_id'], 'idx_prod_bill_proj_party');
                $table->index(['status', 'bill_date'], 'idx_prod_bill_status_date');
            });
        }

        if (!Schema::hasTable('production_bill_lines')) {
            Schema::create('production_bill_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_bill_id')->constrained('production_bills')->cascadeOnDelete();

                $table->foreignId('production_activity_id')->nullable()
                    ->constrained('production_activities')->nullOnDelete();

                $table->decimal('qty', 14, 3)->default(0);
                $table->foreignId('qty_uom_id')->nullable()->constrained('uoms')->nullOnDelete();

                $table->decimal('rate', 14, 2)->default(0);
                $table->foreignId('rate_uom_id')->nullable()->constrained('uoms')->nullOnDelete();

                $table->decimal('amount', 14, 2)->default(0);

                // For audit/traceability
                $table->json('source_meta')->nullable();

                $table->timestamps();

                $table->index(['production_bill_id'], 'idx_prod_bill_line_bill');
                $table->index(['production_activity_id'], 'idx_prod_bill_line_activity');
            });
        }

        // Optional mapping table to prevent double-billing (Phase E1 uses "meta" but we add this to be safe)
        if (!Schema::hasTable('production_bill_dpr_lines')) {
            Schema::create('production_bill_dpr_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_bill_id')->constrained('production_bills')->cascadeOnDelete();
                $table->foreignId('production_dpr_line_id')->constrained('production_dpr_lines')->restrictOnDelete();

                $table->timestamps();

                $table->unique(['production_bill_id', 'production_dpr_line_id'], 'uq_bill_dpr_line');
                $table->index('production_dpr_line_id', 'idx_bill_dpr_line');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_bill_dpr_lines');
        Schema::dropIfExists('production_bill_lines');
        Schema::dropIfExists('production_bills');
    }
};
