<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Production Activities (Master)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_activities')) {
            Schema::create('production_activities', function (Blueprint $table) {
                $table->id();

                $table->string('code', 50)->unique();
                $table->string('name', 200);

                // part | assembly | both
                $table->enum('applies_to', ['part', 'assembly', 'both'])->default('both');
                $table->unsignedInteger('default_sequence')->default(0);

                // Billing settings
                $table->foreignId('billing_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                // manual | kg_from_weight | meter_from_len | sqm_from_area | nos
                $table->string('calculation_method', 50)->default('manual');

                // Workflow flags
                $table->boolean('is_fitupp')->default(false);          // creates assembly & consumes parts
                $table->boolean('requires_machine')->default(false);
                $table->boolean('requires_qc')->default(false);

                $table->boolean('is_active')->default(true);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'default_sequence'], 'idx_prod_act_active_seq');
                $table->index('applies_to', 'idx_prod_act_applies_to');
            });
        }

        // ---------------------------------------------------------------
        // Production Plans (Header)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_plans')) {
            Schema::create('production_plans', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('bom_id')->nullable()->constrained('boms')->nullOnDelete();

                $table->string('plan_number', 50)->unique();
                $table->date('plan_date')->nullable();
                $table->text('remarks')->nullable();

                $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'status'], 'idx_prod_plan_project_status');
                $table->index('bom_id', 'idx_prod_plan_bom');
            });
        }

        // ---------------------------------------------------------------
        // Production Plan Items (Imported from BOM)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_plan_items')) {
            Schema::create('production_plan_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_plan_id')->constrained('production_plans')->cascadeOnDelete();
                $table->foreignId('bom_item_id')->nullable()->constrained('bom_items')->nullOnDelete();

                // part | assembly
                $table->enum('item_type', ['part', 'assembly'])->default('part');

                // Human readable marks
                $table->string('item_code', 80)->nullable();
                $table->string('description', 500)->nullable();
                $table->string('assembly_mark', 100)->nullable();
                $table->string('assembly_type', 100)->nullable();

                $table->unsignedInteger('level')->default(0);
                $table->unsignedInteger('sequence_no')->default(0);

                // Planned quantities
                $table->decimal('planned_qty', 14, 3)->default(0);
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('planned_weight_kg', 14, 3)->nullable();

                // Execution status
                $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
                $table->timestamps();

                $table->index(['production_plan_id', 'status'], 'idx_ppi_plan_status');
                $table->index(['item_type', 'assembly_mark'], 'idx_ppi_type_mark');
            });
        }

        // ---------------------------------------------------------------
        // Plan Item Activities (Route + Rates)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_plan_item_activities')) {
            Schema::create('production_plan_item_activities', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_plan_item_id')
                    ->constrained('production_plan_items')
                    ->cascadeOnDelete();

                $table->foreignId('production_activity_id')
                    ->constrained('production_activities')
                    ->restrictOnDelete();

                $table->unsignedInteger('sequence_no')->default(0);
                $table->boolean('is_enabled')->default(true);

                // Assignment & billing
                $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->foreignId('worker_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('rate', 14, 2)->default(0);
                $table->foreignId('rate_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->date('planned_date')->nullable();

                $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
                $table->timestamps();

                $table->unique(['production_plan_item_id', 'production_activity_id'], 'uq_ppia_item_activity');
                $table->index(['production_activity_id', 'status'], 'idx_ppia_activity_status');
            });
        }

        // ---------------------------------------------------------------
        // DPR Header
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_dprs')) {
            Schema::create('production_dprs', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_plan_id')->constrained('production_plans')->restrictOnDelete();
                $table->foreignId('production_activity_id')->constrained('production_activities')->restrictOnDelete();

                $table->date('dpr_date');
                $table->string('shift', 30)->nullable();

                $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->foreignId('worker_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();

                // Geofence capture
                $table->decimal('geo_latitude', 10, 7)->nullable();
                $table->decimal('geo_longitude', 10, 7)->nullable();
                $table->decimal('geo_accuracy_m', 10, 2)->nullable();
                $table->string('geo_status', 30)->nullable(); // inside|outside|override

                $table->enum('status', ['draft', 'submitted', 'approved', 'cancelled'])->default('draft');
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();

                $table->text('remarks')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['production_plan_id', 'dpr_date'], 'idx_dpr_plan_date');
                $table->index(['production_activity_id', 'dpr_date'], 'idx_dpr_act_date');
                $table->index('status', 'idx_dpr_status');
            });
        }

        // ---------------------------------------------------------------
        // DPR Lines
        // NOTE: avoid FK constraints to production_assemblies to prevent
        // circular migration ordering issues (Phase A).
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_dpr_lines')) {
            Schema::create('production_dpr_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_dpr_id')->constrained('production_dprs')->cascadeOnDelete();

                $table->foreignId('production_plan_item_id')->nullable()
                    ->constrained('production_plan_items')
                    ->nullOnDelete();

                $table->foreignId('production_plan_item_activity_id')->nullable()
                    ->constrained('production_plan_item_activities')
                    ->nullOnDelete();

                // For assembly-level activities after fitup (FK not enforced in Phase A)
                $table->unsignedBigInteger('production_assembly_id')->nullable();

                $table->boolean('is_completed')->default(true);
                $table->text('remarks')->nullable();

                // Billing quantities
                $table->decimal('qty', 14, 3)->default(0);
                $table->foreignId('qty_uom_id')->nullable()->constrained('uoms')->nullOnDelete();

                // Machine / efficiency fields (optional)
                $table->decimal('minutes_spent', 10, 2)->nullable();
                $table->decimal('efficiency_metric', 14, 3)->nullable();

                $table->timestamps();

                $table->index(['production_dpr_id'], 'idx_dpr_line_dpr');
                $table->index(['production_plan_item_id'], 'idx_dpr_line_plan_item');
                $table->index(['production_assembly_id'], 'idx_dpr_line_assembly');
            });
        }

        // ---------------------------------------------------------------
        // Pieces (cut pieces created from stock items)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_pieces')) {
            Schema::create('production_pieces', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('production_plan_id')->nullable()->constrained('production_plans')->nullOnDelete();
                $table->foreignId('production_plan_item_id')->nullable()->constrained('production_plan_items')->nullOnDelete();
                $table->foreignId('production_dpr_line_id')->nullable()->constrained('production_dpr_lines')->nullOnDelete();

                // Mother stock item: from store_stock_items
                $table->foreignId('mother_stock_item_id')->nullable()->constrained('store_stock_items')->nullOnDelete();

                $table->string('piece_number', 80)->unique();
                $table->string('piece_tag', 120)->nullable();

                // Geometry (cut piece)
                $table->unsignedInteger('thickness_mm')->nullable();
                $table->unsignedInteger('width_mm')->nullable();
                $table->unsignedInteger('length_mm')->nullable();
                $table->decimal('weight_kg', 12, 3)->nullable();

                // Traceability snapshot
                $table->string('plate_number', 50)->nullable();
                $table->string('heat_number', 100)->nullable();
                $table->string('mtc_number', 100)->nullable();

                $table->enum('status', ['available', 'consumed', 'scrap'])->default('available');

                $table->timestamps();

                $table->index(['project_id', 'status'], 'idx_piece_proj_status');
                $table->index(['plate_number'], 'idx_piece_plate');
                $table->index(['heat_number'], 'idx_piece_heat');
                $table->index(['mtc_number'], 'idx_piece_mtc');
            });
        }

        // ---------------------------------------------------------------
        // Assemblies (created on fitup)
        // NOTE: production_dpr_line_id stored but FK not enforced (Phase A).
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_assemblies')) {
            Schema::create('production_assemblies', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('production_plan_id')->nullable()->constrained('production_plans')->nullOnDelete();
                $table->foreignId('production_plan_item_id')->nullable()->constrained('production_plan_items')->nullOnDelete();
                $table->unsignedBigInteger('production_dpr_line_id')->nullable();

                $table->string('assembly_mark', 120);
                $table->string('assembly_type', 120)->nullable();
                $table->decimal('weight_kg', 12, 3)->nullable();

                $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
                $table->timestamps();

                $table->index(['project_id', 'assembly_mark'], 'idx_asm_proj_mark');
                $table->index(['production_dpr_line_id'], 'idx_asm_dpr_line');
            });
        }

        // ---------------------------------------------------------------
        // Assembly Components (piece -> assembly)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_assembly_components')) {
            Schema::create('production_assembly_components', function (Blueprint $table) {
                $table->id();

                $table->foreignId('production_assembly_id')->constrained('production_assemblies')->cascadeOnDelete();
                $table->foreignId('production_piece_id')->constrained('production_pieces')->restrictOnDelete();

                $table->timestamps();

                $table->unique(['production_assembly_id', 'production_piece_id'], 'uq_asm_piece');
                $table->index('production_piece_id', 'idx_asm_comp_piece');
            });
        }

        // ---------------------------------------------------------------
        // Remnants (captured during cutting)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('production_remnants')) {
            Schema::create('production_remnants', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('production_plan_id')->nullable()->constrained('production_plans')->nullOnDelete();
                $table->foreignId('production_dpr_line_id')->nullable()->constrained('production_dpr_lines')->nullOnDelete();

                // From which mother stock item
                $table->foreignId('mother_stock_item_id')->nullable()->constrained('store_stock_items')->nullOnDelete();

                // If usable remnant is converted into store_stock_items, store link
                $table->foreignId('remnant_stock_item_id')->nullable()->constrained('store_stock_items')->nullOnDelete();

                $table->unsignedInteger('thickness_mm')->nullable();
                $table->unsignedInteger('width_mm')->nullable();
                $table->unsignedInteger('length_mm')->nullable();
                $table->decimal('weight_kg', 12, 3)->nullable();

                $table->boolean('is_usable')->default(true);
                $table->enum('status', ['available', 'consumed', 'scrap'])->default('available');
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['mother_stock_item_id'], 'idx_remnant_mother');
                $table->index(['is_usable', 'status'], 'idx_remnant_usable_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_remnants');
        Schema::dropIfExists('production_assembly_components');
        Schema::dropIfExists('production_assemblies');
        Schema::dropIfExists('production_pieces');
        Schema::dropIfExists('production_dpr_lines');
        Schema::dropIfExists('production_dprs');
        Schema::dropIfExists('production_plan_item_activities');
        Schema::dropIfExists('production_plan_items');
        Schema::dropIfExists('production_plans');
        Schema::dropIfExists('production_activities');
    }
};
