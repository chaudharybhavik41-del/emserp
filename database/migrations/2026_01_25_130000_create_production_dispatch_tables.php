<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_dispatches')) {
            Schema::create('production_dispatches', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('production_plan_id')->nullable()->constrained('production_plans')->nullOnDelete();
                $table->foreignId('client_party_id')->nullable()->constrained('parties')->nullOnDelete();

                $table->string('dispatch_number', 60)->unique();
                $table->date('dispatch_date')->nullable();

                $table->enum('status', ['draft', 'finalized', 'cancelled'])->default('draft');

                // Logistics
                $table->string('vehicle_number', 50)->nullable();
                $table->string('lr_number', 80)->nullable();
                $table->string('transporter_name', 150)->nullable();

                // Summary
                $table->decimal('total_qty', 14, 3)->default(0);
                $table->decimal('total_weight_kg', 14, 3)->default(0);

                $table->text('remarks')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('finalized_at')->nullable();

                $table->timestamps();

                $table->index(['project_id', 'dispatch_date'], 'idx_prod_dispatch_proj_date');
                $table->index(['status', 'dispatch_date'], 'idx_prod_dispatch_status_date');
            });
        }

        if (! Schema::hasTable('production_dispatch_lines')) {
            Schema::create('production_dispatch_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_dispatch_id')->constrained('production_dispatches')->cascadeOnDelete();

                $table->foreignId('production_plan_item_id')->nullable()
                    ->constrained('production_plan_items')->nullOnDelete();

                $table->decimal('qty', 14, 3)->default(0);
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();

                $table->decimal('weight_kg', 14, 3)->default(0);

                $table->text('remarks')->nullable();

                // Snapshot fields (so dispatch record stays readable even if plan/item changes later)
                $table->json('source_meta')->nullable();

                $table->timestamps();

                $table->index(['production_dispatch_id'], 'idx_prod_dispatch_line_hdr');
                $table->index(['production_plan_item_id'], 'idx_prod_dispatch_line_plan_item');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_dispatch_lines');
        Schema::dropIfExists('production_dispatches');
    }
};
