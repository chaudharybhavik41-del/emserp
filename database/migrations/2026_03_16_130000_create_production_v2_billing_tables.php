<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_billing_rates')) {
            Schema::create('production_v2_billing_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('contractor_party_id')->constrained('parties')->restrictOnDelete();
                $table->string('source_type', 40);
                $table->foreignId('operation_master_id')->nullable()->constrained('production_v2_operation_masters')->nullOnDelete();
                $table->string('qty_basis', 40);
                $table->decimal('rate', 14, 2)->default(0);
                $table->foreignId('rate_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->string('description', 180)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'contractor_party_id', 'source_type'], 'idx_pv2_billing_rate_project_party_source');
            });
        }

        if (! Schema::hasTable('production_v2_bills')) {
            Schema::create('production_v2_bills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('contractor_party_id')->constrained('parties')->restrictOnDelete();
                $table->string('bill_number', 80)->unique();
                $table->date('bill_date')->nullable();
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->string('status', 40)->default('draft');
                $table->string('gst_type', 20)->default('cgst_sgst');
                $table->decimal('gst_rate', 6, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('cgst_total', 14, 2)->default(0);
                $table->decimal('sgst_total', 14, 2)->default(0);
                $table->decimal('igst_total', 14, 2)->default(0);
                $table->decimal('grand_total', 14, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'contractor_party_id'], 'idx_pv2_bill_project_party');
                $table->index(['project_id', 'status'], 'idx_pv2_bill_project_status');
            });
        }

        if (! Schema::hasTable('production_v2_bill_lines')) {
            Schema::create('production_v2_bill_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_v2_bill_id')->constrained('production_v2_bills')->cascadeOnDelete();
                $table->foreignId('billing_rate_id')->nullable()->constrained('production_v2_billing_rates')->nullOnDelete();
                $table->string('source_type', 40);
                $table->foreignId('operation_master_id')->nullable()->constrained('production_v2_operation_masters')->nullOnDelete();
                $table->string('description', 180);
                $table->decimal('qty', 14, 3)->default(0);
                $table->foreignId('qty_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('rate', 14, 2)->default(0);
                $table->foreignId('rate_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('cgst_amount', 14, 2)->default(0);
                $table->decimal('sgst_amount', 14, 2)->default(0);
                $table->decimal('igst_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->json('source_meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_v2_bill_source_links')) {
            Schema::create('production_v2_bill_source_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_v2_bill_id')->constrained('production_v2_bills')->cascadeOnDelete();
                $table->string('source_type', 40);
                $table->unsignedBigInteger('source_id');
                $table->timestamps();

                $table->unique(['source_type', 'source_id'], 'uq_pv2_bill_source_unique');
                $table->index(['production_v2_bill_id'], 'idx_pv2_bill_source_bill');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_bill_source_links');
        Schema::dropIfExists('production_v2_bill_lines');
        Schema::dropIfExists('production_v2_bills');
        Schema::dropIfExists('production_v2_billing_rates');
    }
};
