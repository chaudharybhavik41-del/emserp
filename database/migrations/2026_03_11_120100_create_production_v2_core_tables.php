<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_part_definitions')) {
            Schema::create('production_v2_part_definitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('part_code', 120);
                $table->string('part_name', 200);
                $table->string('part_type', 60);
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('required_qty', 14, 3)->default(0);
                $table->text('description')->nullable();
                $table->foreignId('material_item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->string('material_grade', 120)->nullable();
                $table->string('material_category', 120)->nullable();
                $table->decimal('thickness_mm', 10, 3)->nullable();
                $table->decimal('width_mm', 12, 3)->nullable();
                $table->decimal('length_mm', 12, 3)->nullable();
                $table->decimal('unit_weight_kg', 12, 3)->nullable();
                $table->decimal('unit_area_m2', 12, 4)->nullable();
                $table->decimal('unit_cut_length_m', 12, 4)->nullable();
                $table->decimal('unit_weld_length_m', 12, 4)->nullable();
                $table->boolean('is_interchangeable')->default(true);
                $table->boolean('is_cuttable')->default(true);
                $table->boolean('is_section_item')->default(false);
                $table->boolean('is_bought_out')->default(false);
                $table->string('drawing_ref', 150)->nullable();
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'part_code'], 'uq_prod_v2_part_project_code');
                $table->index(['project_id', 'part_type'], 'idx_prod_v2_part_project_type');
                $table->index(['project_id', 'status'], 'idx_prod_v2_part_project_status');
            });
        }

        if (! Schema::hasTable('production_v2_assemblies')) {
            Schema::create('production_v2_assemblies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('assembly_code', 120);
                $table->string('assembly_name', 200);
                $table->string('assembly_type', 120)->nullable();
                $table->string('span_no', 80)->nullable();
                $table->string('leaf_no', 80)->nullable();
                $table->string('segment_no', 80)->nullable();
                $table->string('girder_no', 80)->nullable();
                $table->string('drawing_ref', 150)->nullable();
                $table->unsignedInteger('sequence_no')->default(0);
                $table->decimal('planned_qty', 14, 3)->default(1);
                $table->decimal('planned_weight_kg', 12, 3)->nullable();
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'assembly_code'], 'uq_prod_v2_assembly_project_code');
                $table->index(['project_id', 'status'], 'idx_prod_v2_assembly_project_status');
            });
        }

        if (! Schema::hasTable('production_v2_assembly_part_requirements')) {
            Schema::create('production_v2_assembly_part_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assembly_id');
                $table->foreignId('part_definition_id');
                $table->decimal('required_qty', 14, 3)->default(0);
                $table->foreignId('uom_id')->nullable();
                $table->unsignedInteger('consumption_sequence')->default(0);
                $table->boolean('is_mandatory')->default(true);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->unique(['assembly_id', 'part_definition_id'], 'uq_prod_v2_assembly_part');
                $table->index(['part_definition_id'], 'idx_prod_v2_req_part');
                $table->foreign('assembly_id', 'fk_pv2_req_asm')->references('id')->on('production_v2_assemblies')->cascadeOnDelete();
                $table->foreign('part_definition_id', 'fk_pv2_req_part')->references('id')->on('production_v2_part_definitions')->restrictOnDelete();
                $table->foreign('uom_id', 'fk_pv2_req_uom')->references('id')->on('uoms')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_cutting_plans')) {
            Schema::create('production_v2_cutting_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('plan_number', 80);
                $table->date('plan_date')->nullable();
                $table->string('grade', 120)->nullable();
                $table->decimal('thickness_mm', 10, 3)->nullable();
                $table->string('source_mode', 40)->default('mixed');
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->unique(['project_id', 'plan_number'], 'uq_prod_v2_cutting_plan_project_number');
                $table->index(['project_id', 'status'], 'idx_prod_v2_cutting_plan_project_status');
            });
        }

        if (! Schema::hasTable('production_v2_cutting_plan_allocations')) {
            Schema::create('production_v2_cutting_plan_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cutting_plan_id');
                $table->foreignId('part_definition_id');
                $table->decimal('planned_qty', 14, 3)->default(0);
                $table->foreignId('mother_stock_item_id')->nullable();
                $table->string('cut_size_text', 200)->nullable();
                $table->decimal('cut_width_mm', 12, 3)->nullable();
                $table->decimal('cut_length_mm', 12, 3)->nullable();
                $table->decimal('thickness_mm', 10, 3)->nullable();
                $table->string('allocation_group', 80)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('cutting_plan_id', 'fk_pv2_cpa_plan')->references('id')->on('production_v2_cutting_plans')->cascadeOnDelete();
                $table->foreign('part_definition_id', 'fk_pv2_cpa_part')->references('id')->on('production_v2_part_definitions')->restrictOnDelete();
                $table->foreign('mother_stock_item_id', 'fk_pv2_cpa_stock')->references('id')->on('store_stock_items')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_cut_batches')) {
            Schema::create('production_v2_cut_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('cutting_plan_id')->nullable();
                $table->foreignId('dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->date('cut_date')->nullable();
                $table->foreignId('mother_stock_item_id')->nullable();
                $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
                $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('shift', 30)->nullable();
                $table->string('plate_number_snapshot', 120)->nullable();
                $table->string('heat_number_snapshot', 120)->nullable();
                $table->string('mtc_number_snapshot', 120)->nullable();
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('cutting_plan_id', 'fk_pv2_cb_plan')->references('id')->on('production_v2_cutting_plans')->nullOnDelete();
                $table->foreign('mother_stock_item_id', 'fk_pv2_cb_stock')->references('id')->on('store_stock_items')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_wip_items')) {
            Schema::create('production_v2_wip_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('part_definition_id');
                $table->foreignId('cut_batch_id')->nullable();
                $table->string('piece_no', 120)->nullable();
                $table->string('lot_no', 120)->nullable();
                $table->decimal('qty', 14, 3)->default(0);
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('thickness_mm', 10, 3)->nullable();
                $table->decimal('width_mm', 12, 3)->nullable();
                $table->decimal('length_mm', 12, 3)->nullable();
                $table->decimal('weight_kg', 12, 3)->nullable();
                $table->foreignId('mother_stock_item_id')->nullable();
                $table->string('plate_number', 120)->nullable();
                $table->string('heat_number', 120)->nullable();
                $table->string('mtc_number', 120)->nullable();
                $table->boolean('is_interchangeable')->default(true);
                $table->foreignId('reserved_for_assembly_id')->nullable();
                $table->string('status', 40)->default('available');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('part_definition_id', 'fk_pv2_wip_part')->references('id')->on('production_v2_part_definitions')->restrictOnDelete();
                $table->foreign('cut_batch_id', 'fk_pv2_wip_batch')->references('id')->on('production_v2_cut_batches')->nullOnDelete();
                $table->foreign('mother_stock_item_id', 'fk_pv2_wip_stock')->references('id')->on('store_stock_items')->nullOnDelete();
                $table->foreign('reserved_for_assembly_id', 'fk_pv2_wip_res_asm')->references('id')->on('production_v2_assemblies')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_fitups')) {
            Schema::create('production_v2_fitups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('assembly_id')->constrained('production_v2_assemblies')->restrictOnDelete();
                $table->foreignId('dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->date('fitup_date')->nullable();
                $table->string('shift', 30)->nullable();
                $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_v2_fitup_consumptions')) {
            Schema::create('production_v2_fitup_consumptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fitup_id');
                $table->foreignId('assembly_id');
                $table->foreignId('wip_item_id');
                $table->decimal('consumed_qty', 14, 3)->default(0);
                $table->foreignId('part_definition_id')->nullable();
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->string('observed_dimension_text', 200)->nullable();
                $table->string('specified_dimension_text', 200)->nullable();
                $table->boolean('dimension_ok')->nullable();
                $table->string('plate_number_snapshot', 120)->nullable();
                $table->string('heat_number_snapshot', 120)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('fitup_id', 'fk_pv2_fuc_fitup')->references('id')->on('production_v2_fitups')->cascadeOnDelete();
                $table->foreign('assembly_id', 'fk_pv2_fuc_asm')->references('id')->on('production_v2_assemblies')->restrictOnDelete();
                $table->foreign('wip_item_id', 'fk_pv2_fuc_wip')->references('id')->on('production_v2_wip_items')->restrictOnDelete();
                $table->foreign('part_definition_id', 'fk_pv2_fuc_part')->references('id')->on('production_v2_part_definitions')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_welding_events')) {
            Schema::create('production_v2_welding_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('assembly_id')->constrained('production_v2_assemblies')->restrictOnDelete();
                $table->foreignId('dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->string('welding_process', 40);
                $table->date('weld_date')->nullable();
                $table->foreignId('welder_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('joint_description', 200)->nullable();
                $table->string('line_no', 120)->nullable();
                $table->decimal('weld_size_mm', 10, 3)->nullable();
                $table->string('wpss_ref', 150)->nullable();
                $table->foreignId('consumable_item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->string('consumable_batch', 120)->nullable();
                $table->string('shielding_gas', 120)->nullable();
                $table->decimal('current_amp', 10, 2)->nullable();
                $table->decimal('voltage', 10, 2)->nullable();
                $table->decimal('travel_speed', 10, 3)->nullable();
                $table->decimal('heat_input', 10, 3)->nullable();
                $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
                $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_v2_inspection_events')) {
            Schema::create('production_v2_inspection_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('assembly_id')->nullable()->constrained('production_v2_assemblies')->nullOnDelete();
                $table->string('inspection_type', 60);
                $table->date('inspection_date')->nullable();
                $table->string('result', 60)->nullable();
                $table->foreignId('related_dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->foreignId('related_welding_event_id')->nullable();
                $table->string('line_no', 120)->nullable();
                $table->string('defect_type', 120)->nullable();
                $table->text('defect_description')->nullable();
                $table->text('repair_action')->nullable();
                $table->string('reoffer_no', 120)->nullable();
                $table->string('retest_result', 60)->nullable();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('inspector_agency', 150)->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('related_welding_event_id', 'fk_pv2_insp_weld')->references('id')->on('production_v2_welding_events')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_trial_assemblies')) {
            Schema::create('production_v2_trial_assemblies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('assembly_group_ref', 150);
                $table->date('trial_date')->nullable();
                $table->foreignId('dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('production_v2_trial_assembly_measurements')) {
            Schema::create('production_v2_trial_assembly_measurements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trial_assembly_id');
                $table->string('parameter_name', 150);
                $table->string('required_dimension', 150)->nullable();
                $table->string('tolerance', 120)->nullable();
                $table->string('actual_dimension', 150)->nullable();
                $table->string('assembly_ref', 150)->nullable();
                $table->boolean('ok_status')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('trial_assembly_id', 'fk_pv2_tam_trial')->references('id')->on('production_v2_trial_assemblies')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_rework_events')) {
            Schema::create('production_v2_rework_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('assembly_id')->nullable()->constrained('production_v2_assemblies')->nullOnDelete();
                $table->foreignId('source_inspection_event_id')->nullable();
                $table->date('rework_date')->nullable();
                $table->string('reason_code', 120)->nullable();
                $table->text('reason_description')->nullable();
                $table->text('action_taken')->nullable();
                $table->foreignId('rework_dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->date('reoffer_date')->nullable();
                $table->string('final_result', 60)->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('source_inspection_event_id', 'fk_pv2_rw_insp')->references('id')->on('production_v2_inspection_events')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_rework_events');
        Schema::dropIfExists('production_v2_trial_assembly_measurements');
        Schema::dropIfExists('production_v2_trial_assemblies');
        Schema::dropIfExists('production_v2_inspection_events');
        Schema::dropIfExists('production_v2_welding_events');
        Schema::dropIfExists('production_v2_fitup_consumptions');
        Schema::dropIfExists('production_v2_fitups');
        Schema::dropIfExists('production_v2_wip_items');
        Schema::dropIfExists('production_v2_cut_batches');
        Schema::dropIfExists('production_v2_cutting_plan_allocations');
        Schema::dropIfExists('production_v2_cutting_plans');
        Schema::dropIfExists('production_v2_assembly_part_requirements');
        Schema::dropIfExists('production_v2_assemblies');
        Schema::dropIfExists('production_v2_part_definitions');
    }
};
