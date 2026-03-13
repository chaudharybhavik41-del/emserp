<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_operation_masters')) {
            Schema::create('production_v2_operation_masters', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 160);
                $table->string('applies_to', 40)->default('assembly');
                $table->string('entry_mode', 40)->default('generic');
                $table->string('entry_route', 160)->nullable();
                $table->boolean('requires_machine')->default(false);
                $table->boolean('requires_qc')->default(false);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['applies_to', 'is_active'], 'idx_pv2_op_master_applies_active');
            });
        }

        if (! Schema::hasTable('production_v2_route_templates')) {
            Schema::create('production_v2_route_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('template_code', 120);
                $table->string('template_name', 180);
                $table->string('applies_to', 40)->default('assembly');
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'template_code'], 'uq_pv2_route_template_project_code');
                $table->index(['project_id', 'applies_to', 'status'], 'idx_pv2_route_template_project_type');
            });
        }

        if (! Schema::hasTable('production_v2_route_template_steps')) {
            Schema::create('production_v2_route_template_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_template_id')->constrained('production_v2_route_templates')->cascadeOnDelete();
                $table->foreignId('operation_master_id')->constrained('production_v2_operation_masters')->restrictOnDelete();
                $table->unsignedInteger('sequence_no')->default(1);
                $table->boolean('is_mandatory')->default(true);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['route_template_id', 'sequence_no'], 'idx_pv2_route_template_step_seq');
            });
        }

        if (! Schema::hasColumn('production_v2_part_definitions', 'route_template_id')) {
            Schema::table('production_v2_part_definitions', function (Blueprint $table) {
                $table->foreignId('route_template_id')->nullable()->after('drawing_ref');
                $table->foreign('route_template_id', 'fk_pv2_part_route_template')
                    ->references('id')->on('production_v2_route_templates')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('production_v2_assemblies', 'route_template_id')) {
            Schema::table('production_v2_assemblies', function (Blueprint $table) {
                $table->foreignId('route_template_id')->nullable()->after('drawing_ref');
                $table->foreign('route_template_id', 'fk_pv2_assembly_route_template')
                    ->references('id')->on('production_v2_route_templates')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_part_route_steps')) {
            Schema::create('production_v2_part_route_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('part_definition_id')->constrained('production_v2_part_definitions')->cascadeOnDelete();
                $table->foreignId('route_template_id')->nullable()->constrained('production_v2_route_templates')->nullOnDelete();
                $table->foreignId('route_template_step_id')->nullable();
                $table->foreignId('operation_master_id')->constrained('production_v2_operation_masters')->restrictOnDelete();
                $table->string('operation_code', 80);
                $table->string('operation_name', 160);
                $table->string('entry_mode', 40)->default('generic');
                $table->string('entry_route', 160)->nullable();
                $table->unsignedInteger('sequence_no')->default(1);
                $table->boolean('is_mandatory')->default(true);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['part_definition_id', 'sequence_no'], 'idx_pv2_part_route_step_seq');
                $table->foreign('route_template_step_id', 'fk_pv2_part_route_tpl_step')
                    ->references('id')->on('production_v2_route_template_steps')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_assembly_route_steps')) {
            Schema::create('production_v2_assembly_route_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assembly_id')->constrained('production_v2_assemblies')->cascadeOnDelete();
                $table->foreignId('route_template_id')->nullable()->constrained('production_v2_route_templates')->nullOnDelete();
                $table->foreignId('route_template_step_id')->nullable();
                $table->foreignId('operation_master_id')->constrained('production_v2_operation_masters')->restrictOnDelete();
                $table->string('operation_code', 80);
                $table->string('operation_name', 160);
                $table->string('entry_mode', 40)->default('generic');
                $table->string('entry_route', 160)->nullable();
                $table->unsignedInteger('sequence_no')->default(1);
                $table->boolean('is_mandatory')->default(true);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['assembly_id', 'sequence_no'], 'idx_pv2_assembly_route_step_seq');
                $table->foreign('route_template_step_id', 'fk_pv2_asm_route_tpl_step')
                    ->references('id')->on('production_v2_route_template_steps')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_operation_events')) {
            Schema::create('production_v2_operation_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('operation_master_id')->constrained('production_v2_operation_masters')->restrictOnDelete();
                $table->foreignId('part_route_step_id')->nullable()->constrained('production_v2_part_route_steps')->nullOnDelete();
                $table->foreignId('assembly_route_step_id')->nullable()->constrained('production_v2_assembly_route_steps')->nullOnDelete();
                $table->foreignId('part_definition_id')->nullable()->constrained('production_v2_part_definitions')->nullOnDelete();
                $table->foreignId('assembly_id')->nullable()->constrained('production_v2_assemblies')->nullOnDelete();
                $table->foreignId('wip_item_id')->nullable()->constrained('production_v2_wip_items')->nullOnDelete();
                $table->foreignId('dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->date('operation_date')->nullable();
                $table->string('shift', 30)->nullable();
                $table->decimal('qty', 14, 3)->default(1);
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
                $table->foreignId('worker_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('result', 60)->nullable();
                $table->string('reference_no', 120)->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 40)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'operation_master_id', 'operation_date'], 'idx_pv2_operation_events_project_op');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_operation_events');
        Schema::dropIfExists('production_v2_assembly_route_steps');
        Schema::dropIfExists('production_v2_part_route_steps');

        if (Schema::hasColumn('production_v2_assemblies', 'route_template_id')) {
            Schema::table('production_v2_assemblies', function (Blueprint $table) {
                $table->dropForeign('fk_pv2_assembly_route_template');
                $table->dropColumn('route_template_id');
            });
        }

        if (Schema::hasColumn('production_v2_part_definitions', 'route_template_id')) {
            Schema::table('production_v2_part_definitions', function (Blueprint $table) {
                $table->dropForeign('fk_pv2_part_route_template');
                $table->dropColumn('route_template_id');
            });
        }

        Schema::dropIfExists('production_v2_route_template_steps');
        Schema::dropIfExists('production_v2_route_templates');
        Schema::dropIfExists('production_v2_operation_masters');
    }
};
